<?php

namespace App\Http\Controllers\Api;

use App\Models\Group;
use App\Models\Settlement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\PersonalAccount;
use App\Models\PersonalTransaction;
use App\Services\PersonalFinanceService;

class SettlementController extends Controller
{
    public function __construct(private PersonalFinanceService $finance) {}
    // لیست تسویه‌های یک گروه
    public function index(Group $group)
    {
        $this->authorize('view', $group);
        
        $settlements = $group->settlements()->with(['debtor', 'creditor', 'debtorPersonalAccount', 'creditorPersonalAccount'])->orderBy('created_at', 'desc')->get();
        
        return response()->json($settlements);
    }

    // ثبت درخواست تسویه (بدهکار به طلبکار)
    public function store(Request $request, Group $group)
    {
        $this->authorize('view', $group);
        
        $request->validate([
            'debtor_id' => 'required|exists:users,id',
            'creditor_id' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
            'receipt_image' => 'nullable|string',
            'payment_method' => 'required|in:cash,bank_transfer,card_to_card,personal_account,other',
            'personal_account_id' => 'nullable|integer|exists:personal_accounts,id',
        ]);
        
        if ((int) $request->debtor_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'فقط بدهکار می‌تواند درخواست تسویه خود را ثبت کند'], 403);
        }
        if ($request->personal_account_id && ! PersonalAccount::whereKey($request->personal_account_id)
            ->where('user_id', $request->user()->id)->where('is_archived', false)->exists()) {
            return response()->json(['message' => 'حساب پرداخت‌کننده معتبر نیست'], 422);
        }
        if ((int) $request->debtor_id === (int) $request->creditor_id) {
            return response()->json(['message' => 'بدهکار و طلبکار نمی‌توانند یک نفر باشند'], 422);
        }

        $memberIds = $group->members()->pluck('user_id')->toArray();
        if (!in_array($request->debtor_id, $memberIds) || !in_array($request->creditor_id, $memberIds)) {
            return response()->json(['message' => 'هر دو نفر باید عضو گروه باشند'], 422);
        }
        
        $balances = $this->balances($group);
        $debtorBalance = $balances[(int) $request->debtor_id] ?? 0;
        $creditorBalance = $balances[(int) $request->creditor_id] ?? 0;
        if ($debtorBalance >= 0 || $creditorBalance <= 0) {
            return response()->json(['message' => 'بدهکار یا طلبکار انتخاب‌شده مانده معتبر ندارد'], 422);
        }
        if ((float) $request->amount > min(abs($debtorBalance), $creditorBalance)) {
            return response()->json(['message' => 'مبلغ تسویه از مانده قابل تسویه بیشتر است'], 422);
        }
        
        $settlement = Settlement::create([
            'group_id' => $group->id,
            'debtor_id' => $request->debtor_id,
            'creditor_id' => $request->creditor_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'debtor_personal_account_id' => $request->personal_account_id,
            'receipt_image' => $request->receipt_image,
            'status' => 'pending',
        ]);

        $settlement->load(['debtor', 'creditor', 'debtorPersonalAccount']);
        Notification::send($group->members, new AppNotification(
            'settlement_created',
            ($settlement->debtor->name ?: 'بدهکار').' درخواست تسویه '.number_format((int) $settlement->amount).' تومان با '.($settlement->creditor->name ?: 'طلبکار').' را ثبت کرد',
            ['group_id' => $group->id, 'settlement_id' => $settlement->id],
        ));
        
        return response()->json($settlement, 201);
    }

    // تأیید تسویه (توسط طلبکار)
    public function approve(Request $request, Settlement $settlement)
    {
        $this->authorize('view', $settlement->group);
        if ((int) $settlement->creditor_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'فقط طلبکار می‌تواند تسویه را تأیید کند'], 403);
        }
        
        if ($settlement->status != 'pending') {
            return response()->json(['message' => 'این درخواست قبلاً بررسی شده است'], 422);
        }

        $request->validate(['personal_account_id' => 'nullable|integer|exists:personal_accounts,id']);
        $creditorAccount = $request->personal_account_id
            ? PersonalAccount::whereKey($request->personal_account_id)->where('user_id', $request->user()->id)->where('is_archived', false)->first()
            : null;
        if ($request->personal_account_id && ! $creditorAccount) {
            return response()->json(['message' => 'حساب مقصد معتبر نیست'], 422);
        }
        $debtorAccount = $settlement->debtorPersonalAccount;
        if ($debtorAccount && $this->finance->balance($debtorAccount) < (int) $settlement->amount) {
            return response()->json(['message' => 'موجودی حساب انتخاب‌شده بدهکار برای این پرداخت کافی نیست'], 422);
        }

        DB::transaction(function () use ($settlement, $debtorAccount, $creditorAccount) {
            $settlement->update([
                'status' => 'approved',
                'settled_at' => now(),
                'creditor_personal_account_id' => $creditorAccount?->id,
            ]);
            if ($debtorAccount) {
                PersonalTransaction::firstOrCreate(
                    ['user_id' => $settlement->debtor_id, 'reference_type' => 'settlement', 'reference_id' => $settlement->id],
                    [
                        'account_id' => $debtorAccount->id, 'type' => 'expense', 'amount' => $settlement->amount,
                        'category' => 'بدهی پرداخت‌شده', 'title' => 'تسویه '.$settlement->group->name,
                        'transaction_date' => now()->toDateString(),
                    ]
                );
            }
            if ($creditorAccount) {
                PersonalTransaction::firstOrCreate(
                    ['user_id' => $settlement->creditor_id, 'reference_type' => 'settlement', 'reference_id' => $settlement->id],
                    [
                        'account_id' => $creditorAccount->id, 'type' => 'income', 'amount' => $settlement->amount,
                        'category' => 'طلب دریافت‌شده', 'title' => 'دریافت تسویه '.$settlement->group->name,
                        'transaction_date' => now()->toDateString(),
                    ]
                );
            }
        });

        Notification::send($settlement->group->members, new AppNotification(
            'settlement_approved',
            'تسویه '.number_format((int) $settlement->amount).' تومان در «'.$settlement->group->name.'» تأیید شد',
            ['group_id' => $settlement->group_id, 'settlement_id' => $settlement->id],
        ));
        
        return response()->json(['message' => 'تسویه با موفقیت تأیید شد']);
    }

    // رد تسویه (توسط طلبکار)
    public function reject(Request $request, Settlement $settlement)
    {
        $this->authorize('view', $settlement->group);
        if ((int) $settlement->creditor_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'فقط طلبکار می‌تواند تسویه را رد کند'], 403);
        }
        
        $request->validate(['rejection_reason' => 'required|string|max:255']);
        
        if ($settlement->status != 'pending') {
            return response()->json(['message' => 'این درخواست قبلاً بررسی شده است'], 422);
        }
        
        $settlement->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        Notification::send($settlement->group->members, new AppNotification(
            'settlement_rejected',
            'تسویه '.number_format((int) $settlement->amount).' تومان در «'.$settlement->group->name.'» رد شد',
            [
                'group_id' => $settlement->group_id,
                'settlement_id' => $settlement->id,
                'reason' => $request->rejection_reason,
            ],
        ));
        
        return response()->json(['message' => 'درخواست تسویه رد شد']);
    }

    private function balances(Group $group): array
    {
        $balances = [];
        foreach ($group->expenses()->with('splits')->get() as $expense) {
            $balances[$expense->payer_id] = ($balances[$expense->payer_id] ?? 0) + (float) $expense->amount;
            foreach ($expense->splits as $split) {
                $balances[$split->user_id] = ($balances[$split->user_id] ?? 0) - (float) $split->share_amount;
            }
        }
        foreach ($group->settlements()->where('status', 'approved')->get() as $settlement) {
            $balances[$settlement->debtor_id] = ($balances[$settlement->debtor_id] ?? 0) + (float) $settlement->amount;
            $balances[$settlement->creditor_id] = ($balances[$settlement->creditor_id] ?? 0) - (float) $settlement->amount;
        }

        return $balances;
    }
}
