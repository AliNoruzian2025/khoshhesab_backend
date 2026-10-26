<?php

namespace App\Services;

use App\Models\PersonalAccount;
use App\Models\User;

class PersonalFinanceService
{
    public function balance(PersonalAccount $account): int
    {
        $base = (int) $account->opening_balance;
        $owned = $account->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount WHEN type = 'transfer' THEN -amount WHEN type = 'adjustment' THEN amount ELSE 0 END), 0) AS total")
            ->value('total');
        $incoming = $account->incomingTransfers()->where('type', 'transfer')->sum('amount');

        return $base + (int) $owned + (int) $incoming;
    }

    public function accountPayload(PersonalAccount $account): array
    {
        return array_merge($account->toArray(), ['balance' => $this->balance($account)]);
    }

    public function sharedPosition(User $user): array
    {
        $receivable = 0;
        $debt = 0;
        foreach ($user->groups()->get() as $group) {
            $groupBalance = 0;
            foreach ($group->expenses()->with('splits')->get() as $expense) {
                if ((int) $expense->payer_id === (int) $user->id) $groupBalance += (int) $expense->amount;
                foreach ($expense->splits as $split) {
                    if ((int) $split->user_id === (int) $user->id) $groupBalance -= (int) $split->share_amount;
                }
            }
            foreach ($group->settlements()->where('status', 'approved')->get() as $settlement) {
                if ((int) $settlement->debtor_id === (int) $user->id) $groupBalance += (int) $settlement->amount;
                if ((int) $settlement->creditor_id === (int) $user->id) $groupBalance -= (int) $settlement->amount;
            }
            $receivable += max($groupBalance, 0);
            $debt += abs(min($groupBalance, 0));
        }

        return [
            'receivable' => $receivable,
            'debt' => $debt,
            'net' => $receivable - $debt,
        ];
    }
}
