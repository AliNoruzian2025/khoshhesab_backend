<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccount;
use App\Models\PersonalTransaction;
use App\Services\PersonalFinanceService;
use Illuminate\Http\Request;

class PersonalTransactionController extends Controller
{
    public function __construct(private PersonalFinanceService $finance) {}

    public function index(Request $request)
    {
        $items = $request->user()->personalTransactions()
            ->with(['account', 'destinationAccount'])
            ->when($request->filled('account_id'), fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('account_id', $request->integer('account_id'))
                    ->orWhere('destination_account_id', $request->integer('account_id'));
            }))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('transaction_date')->orderByDesc('id')->limit(200)->get();
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->validateAccounts($request, $data);
        $this->validateSufficientBalance($data);
        $transaction = $request->user()->personalTransactions()->create($data);
        return response()->json($transaction->load(['account', 'destinationAccount']), 201);
    }

    public function update(Request $request, PersonalTransaction $personalTransaction)
    {
        $this->owned($request, $personalTransaction);
        if ($personalTransaction->reference_type) {
            return response()->json(['message' => 'تراکنش متصل به تسویه فقط از همان تسویه قابل تغییر است'], 422);
        }
        $data = $this->validated($request);
        $this->validateAccounts($request, $data);
        $this->validateSufficientBalance($data, $personalTransaction);
        $personalTransaction->update($data);
        return response()->json($personalTransaction->fresh()->load(['account', 'destinationAccount']));
    }

    public function destroy(Request $request, PersonalTransaction $personalTransaction)
    {
        $this->owned($request, $personalTransaction);
        if ($personalTransaction->reference_type) {
            return response()->json(['message' => 'تراکنش متصل به تسویه قابل حذف مستقیم نیست'], 422);
        }
        $personalTransaction->delete();
        return response()->json(['message' => 'تراکنش حذف شد و موجودی حساب به‌روزرسانی شد']);
    }

    public function categories()
    {
        return response()->json([
            'income' => ['حقوق', 'فروش', 'هدیه', 'سود', 'بازگشت وجه', 'طلب دریافت‌شده', 'سایر'],
            'expense' => ['خوراک', 'خرید', 'مسکن', 'قبوض', 'حمل‌ونقل', 'درمان', 'آموزش', 'تفریح', 'بدهی پرداخت‌شده', 'سایر'],
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:personal_accounts,id'],
            'destination_account_id' => ['nullable', 'integer', 'different:account_id', 'exists:personal_accounts,id'],
            'type' => ['required', 'in:income,expense,transfer,adjustment'],
            'amount' => ['required', 'integer', 'not_in:0'],
            'category' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        if ($data['type'] !== 'adjustment' && $data['amount'] < 1) abort(422, 'مبلغ باید بیشتر از صفر باشد');
        if ($data['type'] === 'transfer' && empty($data['destination_account_id'])) abort(422, 'حساب مقصد انتقال الزامی است');
        if ($data['type'] !== 'transfer') $data['destination_account_id'] = null;
        return $data;
    }

    private function validateAccounts(Request $request, array $data): void
    {
        $ids = array_filter([$data['account_id'], $data['destination_account_id'] ?? null]);
        abort_unless(
            PersonalAccount::where('user_id', $request->user()->id)
                ->where('is_archived', false)
                ->whereIn('id', $ids)->count() === count($ids),
            403,
            'حساب انتخاب‌شده معتبر یا فعال نیست'
        );
    }

    private function validateSufficientBalance(array $data, ?PersonalTransaction $replacing = null): void
    {
        $account = PersonalAccount::findOrFail($data['account_id']);
        $balanceBefore = $this->finance->balance($account);
        if ($replacing) {
            $balanceBefore -= $this->effectOnAccount($replacing, $account->id);
        }

        $newEffect = match ($data['type']) {
            'income' => (int) $data['amount'],
            'expense', 'transfer' => -(int) $data['amount'],
            'adjustment' => (int) $data['amount'],
        };
        abort_if(
            $balanceBefore + $newEffect < 0,
            422,
            'مبلغ هزینه از موجودی حساب انتخاب‌شده بیشتر است'
        );
    }

    private function effectOnAccount(PersonalTransaction $transaction, int $accountId): int
    {
        $effect = 0;
        if ((int) $transaction->account_id === $accountId) {
            $effect += match ($transaction->type) {
                'income' => (int) $transaction->amount,
                'expense', 'transfer' => -(int) $transaction->amount,
                'adjustment' => (int) $transaction->amount,
            };
        }
        if ($transaction->type === 'transfer' && (int) $transaction->destination_account_id === $accountId) {
            $effect += (int) $transaction->amount;
        }
        return $effect;
    }

    private function owned(Request $request, PersonalTransaction $transaction): void
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 403, 'به این تراکنش دسترسی ندارید');
    }
}
