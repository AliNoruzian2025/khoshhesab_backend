<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccount;
use App\Services\PersonalFinanceService;
use Illuminate\Http\Request;

class PersonalAccountController extends Controller
{
    public function __construct(private PersonalFinanceService $finance) {}

    public function index(Request $request)
    {
        return response()->json($request->user()->personalAccounts()
            ->when(! $request->boolean('include_archived'), fn ($q) => $q->where('is_archived', false))
            ->latest()->get()->map(fn ($account) => $this->finance->accountPayload($account)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:bank,card,cash,wallet,other'],
            'opening_balance' => ['required', 'integer'],
            'color' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);
        $account = $request->user()->personalAccounts()->create($data);
        return response()->json($this->finance->accountPayload($account), 201);
    }

    public function update(Request $request, PersonalAccount $personalAccount)
    {
        $this->owned($request, $personalAccount);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['sometimes', 'required', 'in:bank,card,cash,wallet,other'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'note' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_archived' => ['sometimes', 'boolean'],
            'opening_balance' => ['prohibited'],
        ]);
        $personalAccount->update($data);
        return response()->json($this->finance->accountPayload($personalAccount->fresh()));
    }

    public function destroy(Request $request, PersonalAccount $personalAccount)
    {
        $this->owned($request, $personalAccount);
        if ($personalAccount->transactions()->exists() || $personalAccount->incomingTransfers()->exists()) {
            $personalAccount->update(['is_archived' => true]);
            return response()->json(['message' => 'حساب دارای سابقه مالی بود و به‌جای حذف، بایگانی شد', 'archived' => true]);
        }
        $personalAccount->delete();
        return response()->json(['message' => 'حساب حذف شد']);
    }

    private function owned(Request $request, PersonalAccount $account): void
    {
        abort_unless((int) $account->user_id === (int) $request->user()->id, 403, 'به این حساب دسترسی ندارید');
    }
}
