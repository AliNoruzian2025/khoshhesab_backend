<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PersonalFinanceService;
use Illuminate\Http\Request;

class PersonalFinanceDashboardController extends Controller
{
    public function __construct(private PersonalFinanceService $finance) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $accounts = $user->personalAccounts()->where('is_archived', false)->get()
            ->map(fn ($account) => $this->finance->accountPayload($account));
        $total = (int) $accounts->sum('balance');
        $shared = $this->finance->sharedPosition($user);
        $monthStart = now()->startOfMonth()->toDateString();
        $monthTransactions = $user->personalTransactions()->whereDate('transaction_date', '>=', $monthStart)->get();
        $income = (int) $monthTransactions->where('type', 'income')->sum('amount');
        $expense = (int) $monthTransactions->where('type', 'expense')->sum('amount');
        $categoryExpenses = $monthTransactions->where('type', 'expense')->groupBy(fn ($item) => $item->category ?: 'سایر')
            ->map(fn ($items, $category) => ['category' => $category, 'amount' => (int) $items->sum('amount')])
            ->sortByDesc('amount')->values();
        $daily = collect(range(6, 0))->map(function ($offset) use ($user) {
            $date = now()->subDays($offset)->toDateString();
            $items = $user->personalTransactions()->whereDate('transaction_date', $date)->get();
            return [
                'date' => $date,
                'income' => (int) $items->where('type', 'income')->sum('amount'),
                'expense' => (int) $items->where('type', 'expense')->sum('amount'),
            ];
        });
        $monthDaily = collect(range(now()->day - 1, 0))->map(function ($offset) use ($user) {
            $date = now()->subDays($offset)->toDateString();
            $items = $user->personalTransactions()->whereDate('transaction_date', $date)->get();
            return [
                'date' => $date,
                'income' => (int) $items->where('type', 'income')->sum('amount'),
                'expense' => (int) $items->where('type', 'expense')->sum('amount'),
            ];
        });
        $trend = collect(range(5, 0))->map(function ($offset) use ($user) {
            $date = now()->subMonths($offset);
            $items = $user->personalTransactions()->whereYear('transaction_date', $date->year)->whereMonth('transaction_date', $date->month)->get();
            return [
                'month' => $date->format('Y-m'),
                'income' => (int) $items->where('type', 'income')->sum('amount'),
                'expense' => (int) $items->where('type', 'expense')->sum('amount'),
            ];
        });
        $yearTrend = collect(range(11, 0))->map(function ($offset) use ($user) {
            $date = now()->subMonths($offset);
            $items = $user->personalTransactions()->whereYear('transaction_date', $date->year)->whereMonth('transaction_date', $date->month)->get();
            return [
                'month' => $date->format('Y-m'),
                'income' => (int) $items->where('type', 'income')->sum('amount'),
                'expense' => (int) $items->where('type', 'expense')->sum('amount'),
            ];
        });

        return response()->json([
            'accounts' => $accounts,
            'total_balance' => $total,
            'shared_receivable' => $shared['receivable'],
            'shared_debt' => $shared['debt'],
            'net_worth' => $total + $shared['net'],
            'spendable_balance' => $total - $shared['debt'],
            'month_income' => $income,
            'month_expense' => $expense,
            'warning' => $total < $shared['debt'] ? 'موجودی حساب‌های شما برای پرداخت همه بدهی‌های فعلی کافی نیست' : null,
            'trend' => $trend,
            'daily_trend' => $daily,
            'month_daily_trend' => $monthDaily,
            'year_trend' => $yearTrend,
            'category_expenses' => $categoryExpenses,
            'recent_transactions' => $user->personalTransactions()->with(['account', 'destinationAccount'])->orderByDesc('transaction_date')->orderByDesc('id')->limit(10)->get(),
            'goals' => $user->savingsGoals()->latest()->get(),
        ]);
    }
}
