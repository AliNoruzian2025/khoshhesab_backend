<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhoneChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    private const OTP_TTL_SECONDS = 120;

    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[\pL\s‌-]+$/u'],
            'avatar' => ['sometimes', 'nullable', 'string'],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()->id),
            ],
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'avatar', 'email']));

        return response()->json($user->fresh());
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        if ($user->avatar && str_starts_with($user->avatar, 'storage/avatars/')) {
            Storage::disk('public')->delete(str_replace('storage/', '', $user->avatar));
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => 'storage/'.$path]);

        return response()->json($user->fresh());
    }

    public function requestPhoneChange(Request $request)
    {
        $request->validate(['new_phone' => ['required', 'string']]);

        $newPhone = $this->normalizePhone($request->new_phone);
        if (! $newPhone) {
            return response()->json(['message' => 'شماره موبایل نامعتبر است'], 422);
        }
        if ($newPhone === $request->user()->phone) {
            return response()->json(['message' => 'شماره جدید با شماره فعلی یکسان است'], 422);
        }
        if (\App\Models\User::where('phone', $newPhone)->exists()) {
            return response()->json(['message' => 'این شماره قبلاً استفاده شده است'], 409);
        }

        $completedChanges = PhoneChangeRequest::where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDay())
            ->count();
        if ($completedChanges >= 2) {
            return response()->json([
                'message' => 'در هر ۲۴ ساعت حداکثر ۲ بار می‌توانید شماره را تغییر دهید',
            ], 429);
        }

        $cooldownKey = "otp:cooldown:change_phone:{$newPhone}";
        $expiresAt = Cache::get($cooldownKey);
        if ($expiresAt && $expiresAt > now()->timestamp) {
            $pending = PhoneChangeRequest::where('user_id', $request->user()->id)
                ->where('new_phone', $newPhone)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            return response()->json([
                'message' => 'تا پایان زمان اعتبار کد امکان ارسال مجدد وجود ندارد',
                'request_id' => $pending?->id,
                'expires_at' => $expiresAt,
                'retry_after' => $expiresAt - now()->timestamp,
            ], 429);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['apiKey' => config('services.msgway.api_key')])
                ->post('https://api.msgway.com/send', [
                    'mobile' => $this->providerPhone($newPhone),
                    'method' => 'sms',
                    'templateID' => (int) config('services.msgway.template_id', 19026),
                ]);

            if (! $response->successful()
                || strtolower((string) $response->json('status')) !== 'success') {
                return response()->json(['message' => 'ارسال پیامک ناموفق بود'], 502);
            }

            $expires = now()->addSeconds(self::OTP_TTL_SECONDS);
            $changeRequest = PhoneChangeRequest::create([
                'user_id' => $request->user()->id,
                'old_phone' => $request->user()->phone,
                'new_phone' => $newPhone,
                'expires_at' => $expires,
                'status' => 'pending',
            ]);
            Cache::put($cooldownKey, $expires->timestamp, self::OTP_TTL_SECONDS);

            return response()->json([
                'message' => 'کد تأیید به شماره جدید ارسال شد',
                'request_id' => $changeRequest->id,
                'expires_at' => $expires->timestamp,
                'retry_after' => self::OTP_TTL_SECONDS,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Phone change OTP send failed', ['exception' => $exception->getMessage()]);

            return response()->json(['message' => 'خطا در ارتباط با سامانه پیامک'], 502);
        }
    }

    public function verifyPhoneChange(Request $request)
    {
        $request->validate([
            'request_id' => ['required', 'integer'],
            'otp' => ['required', 'digits:7'],
        ]);

        $changeRequest = PhoneChangeRequest::whereKey($request->request_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (! $changeRequest) {
            return response()->json(['message' => 'درخواست نامعتبر است'], 404);
        }
        if ($changeRequest->expires_at->isPast()) {
            $changeRequest->update(['status' => 'expired']);

            return response()->json(['message' => 'زمان اعتبار کد به پایان رسیده است'], 410);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['apiKey' => config('services.msgway.api_key')])
                ->post('https://api.msgway.com/otp/verify', [
                    'OTP' => $request->otp,
                    'mobile' => $this->providerPhone($changeRequest->new_phone),
                ]);

            if (! $response->successful()
                || strtolower((string) $response->json('status')) !== 'success') {
                return response()->json(['message' => 'کد تأیید اشتباه است'], 422);
            }

            DB::transaction(function () use ($request, $changeRequest) {
                $request->user()->update(['phone' => $changeRequest->new_phone]);
                $changeRequest->update(['status' => 'completed', 'completed_at' => now()]);
            });

            Cache::forget("otp:cooldown:change_phone:{$changeRequest->new_phone}");

            return response()->json([
                'message' => 'شماره موبایل با موفقیت تغییر کرد',
                'phone' => $changeRequest->new_phone,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Phone change OTP verification failed', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'خطا در اعتبارسنجی کد'], 502);
        }
    }

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
}
