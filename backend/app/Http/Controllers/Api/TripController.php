<?php

namespace App\Http\Controllers\Api;

use App\Models\Group;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TripController extends Controller
{
    // لیست دنگ‌های فعال
    public function index(Request $request)
    {
        $trips = $request->user()->groups()
            ->where('type', 'dang')
            ->where('is_active', true)
            ->get();
        
        return response()->json($trips);
    }

    // شروع دنگ جدید
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);
        
        $trip = Group::create([
            'name' => $request->name,
            'type' => 'dang',
            'creator_id' => $request->user()->id,
            'invite_code' => strtoupper(\Illuminate\Support\Str::random(6)),
            'require_approval' => $request->require_approval ?? true,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);
        
        $trip->members()->attach($request->user()->id, ['joined_at' => now()]);
        
        return response()->json($trip, 201);
    }

    // پایان دنگ و محاسبه نهایی
    public function end(Group $trip)
    {
        $this->authorize('manage', $trip);

        if ($trip->type != 'dang') {
            return response()->json(['message' => 'این گروه از نوع دنگ نیست'], 422);
        }
        
        // محاسبه مانده نهایی
        $balances = $this->calculateBalances($trip);
        
        $trip->update([
            'is_active' => false,
            'end_date' => now(),
        ]);
        
        return response()->json([
            'message' => 'دنگ به پایان رسید',
            'final_balances' => $balances
        ]);
    }

    // تمدید دنگ (برای حساب‌ها در اینجا نیازی نیست، فقط برای dang)
    public function renew(Request $request, Group $trip)
    {
        $this->authorize('manage', $trip);

        if ($trip->type != 'dang') {
            return response()->json(['message' => 'این گروه از نوع دنگ نیست'], 422);
        }
        
        $request->validate(['new_end_date' => 'required|date|after:today']);
        
        $trip->update(['end_date' => $request->new_end_date]);
        
        return response()->json(['message' => 'زمان دنگ تمدید شد', 'end_date' => $trip->end_date]);
    }

    private function calculateBalances($group)
    {
        $expenses = $group->expenses()->with('splits')->get();
        $balances = [];
        
        foreach ($expenses as $expense) {
            $balances[$expense->payer_id] = ($balances[$expense->payer_id] ?? 0) + $expense->amount;
            foreach ($expense->splits as $split) {
                $balances[$split->user_id] = ($balances[$split->user_id] ?? 0) - $split->share_amount;
            }
        }
        
        $members = $group->members;
        $result = [];
        foreach ($members as $member) {
            $balance = $balances[$member->id] ?? 0;
            $result[] = [
                'user_id' => $member->id,
                'name' => $member->name,
                'balance' => $balance,
                'status' => $balance > 0 ? 'creditor' : ($balance < 0 ? 'debtor' : 'settled')
            ];
        }
        
        return $result;
    }
}
