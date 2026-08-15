<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AppNotification;

class ExpenseController extends Controller
{
    // لیست هزینه‌های یک گروه
    public function index(Group $group)
    {
        $this->authorize('view', $group);
        
        $expenses = $group->expenses()->with(['payer', 'splits.user'])->orderBy('expense_date', 'desc')->get();
        
        return response()->json($expenses);
    }

    // ثبت هزینه جدید
    public function store(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        if (! $group->is_active) {
            return response()->json(['message' => 'این دنگ یا حساب غیرفعال است و هزینه جدید نمی‌پذیرد'], 422);
        }

        if ($group->type === 'account') {
            $cutoffDate = $group->reminder_date ?: $group->due_date;
            if ($cutoffDate && now()->startOfDay()->greaterThanOrEqualTo($cutoffDate->copy()->startOfDay())) {
                return response()->json([
                    'message' => 'موعد تسویه حساب فرا رسیده و ثبت هزینه جدید بسته شده است',
                ], 422);
            }
        }
        
        $request->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'payer_id' => 'required|exists:users,id',
            'splits' => 'required|array|min:1',
            'splits.*.user_id' => 'required|exists:users,id',
            'splits.*.share_amount' => 'required|integer|min:0',
            'receipt_image' => 'nullable|string'
        ]);
        
        $memberIds = $group->members()->pluck('users.id')->map(fn ($id) => (int) $id);
        $splitUserIds = collect($request->splits)->pluck('user_id')->map(fn ($id) => (int) $id);
        if (! $memberIds->contains((int) $request->payer_id)
            || $splitUserIds->diff($memberIds)->isNotEmpty()) {
            return response()->json(['message' => 'پرداخت‌کننده و صاحبان سهم باید عضو گروه باشند'], 422);
        }
        if ($splitUserIds->duplicates()->isNotEmpty()) {
            return response()->json(['message' => 'سهم هر عضو فقط یک بار قابل ثبت است'], 422);
        }
        
        // چک کن مجموع سهم‌ها با مبلغ کل برابر باشد
        $totalShares = collect($request->splits)->sum('share_amount');
        if ((int) $totalShares !== (int) $request->amount) {
            return response()->json(['message' => 'مجموع سهم‌ها باید برابر با مبلغ کل باشد'], 422);
        }
        
        $expense = DB::transaction(function () use ($request, $group) {
            $expense = Expense::create([
                'group_id' => $group->id,
                'payer_id' => $request->payer_id,
                'amount' => $request->amount,
                'description' => $request->description,
                'receipt_image' => $request->receipt_image,
                'expense_date' => $request->expense_date,
            ]);

            $expense->splits()->createMany($request->splits);

            return $expense;
        });

        Notification::send($group->members, new AppNotification(
            'expense_created',
            'هزینه «'.$expense->description.'» به مبلغ '.number_format((int) $expense->amount).' تومان در «'.$group->name.'» ثبت شد',
            ['group_id' => $group->id, 'expense_id' => $expense->id],
        ));
        
        return response()->json($expense->load(['payer', 'splits.user']), 201);
    }

    // نمایش جزئیات یک هزینه
    public function show(Expense $expense)
    {
        $this->authorize('view', $expense->group);
        
        return response()->json($expense->load(['payer', 'splits.user']));
    }

    // ویرایش هزینه
    public function update(Request $request, Expense $expense)
    {
        $this->authorize('view', $expense->group);

        if ($this->expensesAreLocked($expense->group)) {
            return response()->json(['message' => 'پس از بسته‌شدن دوره، هزینه قابل ویرایش نیست'], 422);
        }
        
        $request->validate([
            'amount' => 'integer|min:1',
            'description' => 'string|max:255',
            'expense_date' => 'date',
            'payer_id' => 'exists:users,id',
            'splits' => 'array|min:1',
            'splits.*.user_id' => 'required_with:splits|exists:users,id',
            'splits.*.share_amount' => 'required_with:splits|integer|min:0',
            'receipt_image' => 'nullable|string'
        ]);
        
        if ($request->has('payer_id')) {
            if (!$expense->group->members()->where('user_id', $request->payer_id)->exists()) {
                return response()->json(['message' => 'پرداخت‌کننده عضو گروه نیست'], 422);
            }
        }
        
        if ($request->has('splits')) {
            $memberIds = $expense->group->members()->pluck('users.id')->map(fn ($id) => (int) $id);
            $splitUserIds = collect($request->splits)->pluck('user_id')->map(fn ($id) => (int) $id);
            if ($splitUserIds->diff($memberIds)->isNotEmpty() || $splitUserIds->duplicates()->isNotEmpty()) {
                return response()->json(['message' => 'تقسیم هزینه شامل عضو نامعتبر یا تکراری است'], 422);
            }

            $amount = $request->input('amount', $expense->amount);
            if ((int) collect($request->splits)->sum('share_amount') !== (int) $amount) {
                return response()->json(['message' => 'مجموع سهم‌ها باید برابر با مبلغ کل باشد'], 422);
            }
        }

        DB::transaction(function () use ($request, $expense) {
            $expense->update($request->only(['amount', 'description', 'expense_date', 'payer_id', 'receipt_image']));

            if ($request->has('splits')) {
                $expense->splits()->delete();
                $expense->splits()->createMany($request->splits);
            }
        });
        
        return response()->json($expense->load(['payer', 'splits.user']));
    }

    // حذف هزینه
    public function destroy(Expense $expense)
    {
        $this->authorize('view', $expense->group);

        if ($this->expensesAreLocked($expense->group)) {
            return response()->json(['message' => 'پس از بسته‌شدن دوره، هزینه قابل حذف نیست'], 422);
        }
        
        $expense->delete();
        
        return response()->json(['message' => 'هزینه با موفقیت حذف شد']);
    }

    private function expensesAreLocked(Group $group): bool
    {
        if (! $group->is_active) {
            return true;
        }

        $cutoffDate = $group->type === 'account' ? ($group->reminder_date ?: $group->due_date) : null;

        return $cutoffDate
            && now()->startOfDay()->greaterThanOrEqualTo($cutoffDate->copy()->startOfDay());
    }
}
