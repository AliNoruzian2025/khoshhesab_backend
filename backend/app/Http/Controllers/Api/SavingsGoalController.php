<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsGoal;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;

class SavingsGoalController extends Controller
{
    public function index(Request $request) { return response()->json($request->user()->savingsGoals()->latest()->get()); }

    public function store(Request $request)
    {
        $data = $this->validated($request, false);
        $data['is_completed'] = $data['saved_amount'] >= $data['target_amount'];
        $goal = $request->user()->savingsGoals()->create($data);
        if ($goal->is_completed) {
            $request->user()->notify(new AppNotification(
                'savings_goal_completed',
                'تبریک! به هدف پس‌انداز «'.$goal->title.'» رسیدید 🎉',
                ['savings_goal_id' => $goal->id],
            ));
        }
        return response()->json($goal, 201);
    }

    public function update(Request $request, SavingsGoal $savingsGoal)
    {
        $this->owned($request, $savingsGoal);
        $wasCompleted = $savingsGoal->is_completed;
        $data = $this->validated($request, true);
        $data['is_completed'] = ($data['saved_amount'] ?? $savingsGoal->saved_amount) >= ($data['target_amount'] ?? $savingsGoal->target_amount);
        $savingsGoal->update($data);
        if (! $wasCompleted && $savingsGoal->is_completed) {
            $request->user()->notify(new AppNotification(
                'savings_goal_completed',
                'تبریک! به هدف پس‌انداز «'.$savingsGoal->title.'» رسیدید 🎉',
                ['savings_goal_id' => $savingsGoal->id],
            ));
        }
        return response()->json($savingsGoal->fresh());
    }

    public function destroy(Request $request, SavingsGoal $savingsGoal)
    {
        $this->owned($request, $savingsGoal); $savingsGoal->delete();
        return response()->json(['message' => 'هدف پس‌انداز حذف شد']);
    }

    private function validated(Request $request, bool $updating): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'saved_amount' => ['required', 'integer', 'min:0'],
            'target_date' => $updating ? ['nullable', 'date'] : ['nullable', 'date', 'after_or_equal:today'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function owned(Request $request, SavingsGoal $goal): void
    {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 403, 'به این هدف دسترسی ندارید');
    }
}
