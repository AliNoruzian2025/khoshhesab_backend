<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PhoneAuthController extends Controller
{
    private const OTP_TTL_SECONDS = 120;

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($phone, '0098')) {
            $phone = substr($phone, 4);
        } elseif (str_starts_with($phone, '98')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '9')) {
            $phone = '0'.$phone;
        }

        return preg_match('/^09\d{9}$/', $phone) ? $phone : null;
    }

    private function providerPhone(string $phone): string
    {
        return '98'.substr($phone, 1);
    }

    private function cooldownKey(string $purpose, string $phone): string
    {
        return "otp:cooldown:{$purpose}:{$phone}";
    }

    private function verifiedKey(string $purpose, string $phone): string
    {
        return "otp:verified:{$purpose}:{$phone}";
    }

    private function sendProviderOtp(string $phone): bool
    {
        $response = Http::timeout(10)
            ->withHeaders(['apiKey' => config('services.msgway.api_key')])
            ->post('https://api.msgway.com/send', [
                'mobile' => $this->providerPhone($phone),
                'method' => 'sms',
                'templateID' => (int) config('services.msgway.template_id', 19026),
            ]);

        Log::info('MSGWAY OTP send', ['status' => $response->status()]);

        return $response->successful()
            && strtolower((string) $response->json('status')) === 'success';
    }

    private function verifyProviderOtp(string $phone, string $otp): bool
    {
        $response = Http::timeout(10)
            ->withHeaders(['apiKey' => config('services.msgway.api_key')])
            ->post('https://api.msgway.com/otp/verify', [
                'OTP' => $otp,
                'mobile' => $this->providerPhone($phone),
            ]);

        Log::info('MSGWAY OTP verify', ['status' => $response->status()]);

        return $response->successful()
            && strtolower((string) $response->json('status')) === 'success';
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => ['required', 'string'],
            'purpose' => ['required', Rule::in(['register', 'reset_password'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $phone = $this->normalizePhone($request->mobile);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if ($request->purpose === 'register' && $user?->is_completed) {
            return response()->json([
                'message' => 'شما قبلاً ثبت‌نام کرده‌اید. لطفاً وارد شوید.',
                'action' => 'login',
            ], 409);
        }
        if ($request->purpose === 'reset_password' && ! $user) {
            return response()->json(['message' => 'شماره شما در سیستم ثبت نشده است'], 404);
        }

        $cooldownKey = $this->cooldownKey($request->purpose, $phone);
        $expiresAt = Cache::get($cooldownKey);
        if ($expiresAt && $expiresAt > now()->timestamp) {
            return response()->json([
                'message' => 'تا پایان زمان اعتبار کد امکان ارسال مجدد وجود ندارد',
                'expires_at' => $expiresAt,
                'retry_after' => $expiresAt - now()->timestamp,
            ], 429);
        }

        try {
            if (! $this->sendProviderOtp($phone)) {
                return response()->json(['message' => 'ارسال پیامک ناموفق بود'], 502);
            }

            $expiresAt = now()->addSeconds(self::OTP_TTL_SECONDS)->timestamp;
            Cache::put($cooldownKey, $expiresAt, self::OTP_TTL_SECONDS);
            Cache::forget($this->verifiedKey($request->purpose, $phone));

            return response()->json([
                'message' => 'کد تأیید ارسال شد',
                'expires_at' => $expiresAt,
                'retry_after' => self::OTP_TTL_SECONDS,
            ]);
        } catch (\Throwable $exception) {
            Log::error('OTP send failed', ['exception' => $exception->getMessage()]);

            return response()->json(['message' => 'خطا در ارتباط با سامانه پیامک'], 502);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => ['required', 'string'],
            'otp' => ['required', 'digits:7'],
            'purpose' => ['required', Rule::in(['register', 'reset_password'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $phone = $this->normalizePhone($request->mobile);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $expiresAt = Cache::get($this->cooldownKey($request->purpose, $phone));
        if (! $expiresAt || $expiresAt < now()->timestamp) {
            return response()->json(['message' => 'زمان اعتبار کد به پایان رسیده است'], 410);
        }

        try {
            if (! $this->verifyProviderOtp($phone, $request->otp)) {
                return response()->json(['message' => 'کد تأیید اشتباه است'], 422);
            }

            Cache::put(
                $this->verifiedKey($request->purpose, $phone),
                true,
                self::OTP_TTL_SECONDS
            );

            $user = User::where('phone', $phone)->first();

            return response()->json([
                'message' => 'کد تأیید صحیح است',
                'exists' => (bool) $user,
                'completed' => (bool) ($user?->is_completed),
            ]);
        } catch (\Throwable $exception) {
            Log::error('OTP verification failed', ['exception' => $exception->getMessage()]);

            return response()->json(['message' => 'خطا در اعتبارسنجی کد'], 502);
        }
    }

    public function otpStatus(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string'],
            'purpose' => ['required', Rule::in(['register', 'reset_password'])],
        ]);

        $phone = $this->normalizePhone($request->mobile);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $expiresAt = Cache::get($this->cooldownKey($request->purpose, $phone));
        $remaining = max(0, (int) $expiresAt - now()->timestamp);

        return response()->json([
            'active' => $remaining > 0,
            'expires_at' => $remaining > 0 ? (int) $expiresAt : null,
            'retry_after' => $remaining,
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s‌-]+$/u'],
            'password' => ['required', 'string', 'min:6', 'regex:/^[A-Za-z0-9]+$/', 'confirmed'],
        ], [
            'name.regex' => 'نام و نام خانوادگی نباید شامل عدد یا نشانه‌های نامعتبر باشد.',
            'password.regex' => 'رمز عبور فقط می‌تواند شامل حروف انگلیسی و اعداد باشد.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }
        if (! Cache::pull($this->verifiedKey('register', $phone), false)) {
            return response()->json(['message' => 'لطفاً ابتدا کد تأیید را وارد کنید'], 403);
        }
        if (User::where('phone', $phone)->where('is_completed', true)->exists()) {
            return response()->json(['message' => 'این شماره قبلاً ثبت‌نام کرده است'], 409);
        }

        $user = DB::transaction(function () use ($request, $phone) {
            $user = User::updateOrCreate(
                ['phone' => $phone],
                [
                    'name' => trim($request->name),
                    'password' => Hash::make($request->password),
                    'is_completed' => true,
                ]
            );
            $user->tokens()->delete();

            return $user;
        });

        return response()->json([
            'message' => 'ثبت‌نام با موفقیت انجام شد',
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ], 201);
    }

    public function checkUserExists(Request $request)
    {
        $request->validate(['phone' => ['required', 'string']]);
        $phone = $this->normalizePhone($request->phone);

        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $user = User::where('phone', $phone)->first();

        return response()->json([
            'exists' => (bool) $user,
            'completed' => (bool) ($user?->is_completed),
        ]);
    }

    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $phone = $this->normalizePhone($request->phone);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return response()->json(['message' => 'حساب کاربری با این شماره یافت نشد'], 404);
        }
        if (! $user->password || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'رمز عبور اشتباه است'], 401);
        }
        if (! $user->is_completed) {
            return response()->json(['message' => 'لطفاً ثبت‌نام را کامل کنید'], 422);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'ورود موفق',
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'regex:/^[A-Za-z0-9]+$/', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        if (! $phone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return response()->json(['message' => 'شماره شما در سیستم ثبت نشده است'], 404);
        }
        if (! Cache::pull($this->verifiedKey('reset_password', $phone), false)) {
            return response()->json(['message' => 'لطفاً ابتدا کد تأیید را وارد کنید'], 403);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'خروج موفق']);
    }
}
