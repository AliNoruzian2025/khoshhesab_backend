<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Group;
use App\Models\PersonalAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_and_remove_personal_transaction_categories(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $categories = $this->getJson('/api/personal-finance/categories')->assertOk()->json();
        $this->assertNotEmpty($categories['income']);
        $this->assertNotEmpty($categories['expense']);

        $created = $this->postJson('/api/personal-finance/categories', [
            'type' => 'expense', 'name' => 'هزینه حیوان خانگی',
        ])->assertCreated()->json('category');
        $this->assertSame('هزینه حیوان خانگی', $created['name']);

        Sanctum::actingAs($other);
        $this->deleteJson('/api/personal-finance/categories/'.$created['id'])->assertForbidden();

        Sanctum::actingAs($user);
        $this->deleteJson('/api/personal-finance/categories/'.$created['id'])
            ->assertOk()
            ->assertJsonMissing(['name' => 'هزینه حیوان خانگی']);
    }

    public function test_account_balances_follow_income_expense_transfer_and_adjustment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $first = $this->postJson('/api/personal-accounts', [
            'name' => 'کارت اصلی', 'type' => 'card', 'opening_balance' => 1000000,
        ])->assertCreated()->json('id');
        $second = $this->postJson('/api/personal-accounts', [
            'name' => 'کیف پول', 'type' => 'wallet', 'opening_balance' => 100000,
        ])->assertCreated()->json('id');

        $this->postJson('/api/personal-transactions', [
            'account_id' => $first, 'type' => 'income', 'amount' => 500000,
            'title' => 'حقوق', 'category' => 'حقوق', 'transaction_date' => today()->toDateString(),
        ])->assertCreated();
        $this->postJson('/api/personal-transactions', [
            'account_id' => $first, 'type' => 'expense', 'amount' => 200000,
            'title' => 'خرید', 'category' => 'خرید', 'transaction_date' => today()->toDateString(),
        ])->assertCreated();
        $this->postJson('/api/personal-transactions', [
            'account_id' => $first, 'destination_account_id' => $second, 'type' => 'transfer',
            'amount' => 300000, 'title' => 'انتقال', 'transaction_date' => today()->toDateString(),
        ])->assertCreated();

        $accounts = $this->getJson('/api/personal-accounts')->assertOk()->json();
        $this->assertSame(1000000, collect($accounts)->firstWhere('id', $first)['balance']);
        $this->assertSame(400000, collect($accounts)->firstWhere('id', $second)['balance']);
    }

    public function test_financial_data_is_private_and_used_for_spendable_balance(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = PersonalAccount::create([
            'user_id' => $other->id, 'name' => 'محرمانه', 'type' => 'bank', 'opening_balance' => 9000000,
        ]);
        Sanctum::actingAs($user);
        $this->putJson("/api/personal-accounts/{$foreign->id}", ['name' => 'دستکاری'])
            ->assertForbidden();

        $account = PersonalAccount::create([
            'user_id' => $user->id, 'name' => 'من', 'type' => 'card', 'opening_balance' => 1000000,
        ]);
        $group = Group::create([
            'name' => 'خانه', 'type' => 'dang', 'creator_id' => $other->id,
            'invite_code' => 'PFN123', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$user->id, $other->id]);
        $expense = Expense::create([
            'group_id' => $group->id, 'payer_id' => $other->id, 'amount' => 300000,
            'description' => 'قبض', 'expense_date' => now(),
        ]);
        $expense->splits()->create(['user_id' => $user->id, 'share_amount' => 300000]);

        $this->getJson('/api/personal-finance/dashboard')->assertOk()
            ->assertJsonPath('total_balance', 1000000)
            ->assertJsonPath('shared_debt', 300000)
            ->assertJsonPath('spendable_balance', 700000)
            ->assertJsonCount(7, 'daily_trend')
            ->assertJsonCount((int) now()->day, 'month_daily_trend')
            ->assertJsonCount(12, 'year_trend');
    }

    public function test_approved_settlement_updates_both_selected_personal_accounts_once(): void
    {
        $debtor = User::factory()->create();
        $creditor = User::factory()->create();
        $debtorAccount = PersonalAccount::create(['user_id' => $debtor->id, 'name' => 'بدهکار', 'type' => 'card', 'opening_balance' => 500000]);
        $creditorAccount = PersonalAccount::create(['user_id' => $creditor->id, 'name' => 'طلبکار', 'type' => 'card', 'opening_balance' => 100000]);
        $group = Group::create([
            'name' => 'سفر', 'type' => 'dang', 'creator_id' => $creditor->id,
            'invite_code' => 'PAY123', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$debtor->id, $creditor->id]);
        $expense = Expense::create(['group_id' => $group->id, 'payer_id' => $creditor->id, 'amount' => 200000, 'description' => 'هتل', 'expense_date' => now()]);
        $expense->splits()->create(['user_id' => $debtor->id, 'share_amount' => 200000]);

        Sanctum::actingAs($debtor);
        $settlementId = $this->postJson("/api/groups/{$group->id}/settlements", [
            'debtor_id' => $debtor->id, 'creditor_id' => $creditor->id, 'amount' => 200000,
            'payment_method' => 'personal_account', 'personal_account_id' => $debtorAccount->id,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($creditor);
        $this->postJson("/api/settlements/{$settlementId}/approve", ['personal_account_id' => $creditorAccount->id])->assertOk();
        $this->postJson("/api/settlements/{$settlementId}/approve", ['personal_account_id' => $creditorAccount->id])->assertUnprocessable();
        $this->assertDatabaseCount('personal_transactions', 2);
        $this->getJson('/api/personal-accounts')->assertJsonPath('0.balance', 300000);
    }

    public function test_receivables_and_debts_from_different_groups_do_not_cancel_each_other(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PersonalAccount::create(['user_id' => $user->id, 'name' => 'اصلی', 'type' => 'card', 'opening_balance' => 1000000]);

        $debtGroup = Group::create(['name' => 'بدهی', 'type' => 'dang', 'creator_id' => $other->id, 'invite_code' => 'DEBT12', 'start_date' => now(), 'is_active' => true]);
        $debtGroup->members()->attach([$user->id, $other->id]);
        $debtExpense = Expense::create(['group_id' => $debtGroup->id, 'payer_id' => $other->id, 'amount' => 300000, 'description' => 'خرید', 'expense_date' => now()]);
        $debtExpense->splits()->create(['user_id' => $user->id, 'share_amount' => 300000]);

        $creditGroup = Group::create(['name' => 'طلب', 'type' => 'dang', 'creator_id' => $user->id, 'invite_code' => 'CRED12', 'start_date' => now(), 'is_active' => true]);
        $creditGroup->members()->attach([$user->id, $other->id]);
        $creditExpense = Expense::create(['group_id' => $creditGroup->id, 'payer_id' => $user->id, 'amount' => 200000, 'description' => 'قبض', 'expense_date' => now()]);
        $creditExpense->splits()->create(['user_id' => $other->id, 'share_amount' => 200000]);

        Sanctum::actingAs($user);
        $this->getJson('/api/personal-finance/dashboard')->assertOk()
            ->assertJsonPath('shared_debt', 300000)
            ->assertJsonPath('shared_receivable', 200000)
            ->assertJsonPath('net_worth', 900000)
            ->assertJsonPath('spendable_balance', 700000);
    }

    public function test_account_with_history_is_archived_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'قدیمی', 'type' => 'card', 'opening_balance' => 100000]);
        $user->personalTransactions()->create([
            'account_id' => $account->id, 'type' => 'expense', 'amount' => 10000,
            'title' => 'خرید', 'transaction_date' => today()->toDateString(),
        ]);

        $this->deleteJson("/api/personal-accounts/{$account->id}")->assertOk()->assertJsonPath('archived', true);
        $this->assertDatabaseHas('personal_accounts', ['id' => $account->id, 'is_archived' => true]);
        $this->getJson('/api/personal-accounts')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/personal-accounts?include_archived=1')->assertOk()->assertJsonCount(1);
        $this->putJson("/api/personal-accounts/{$account->id}", ['is_archived' => false])->assertOk();
        $this->assertDatabaseHas('personal_accounts', ['id' => $account->id, 'is_archived' => false]);
    }

    public function test_personal_account_caption_can_be_edited_up_to_one_hundred_characters(): void
    {
        $user = User::factory()->create();
        $account = PersonalAccount::create([
            'user_id' => $user->id, 'name' => 'کارت اصلی', 'type' => 'card',
            'opening_balance' => 0, 'note' => 'کپشن اولیه',
        ]);
        Sanctum::actingAs($user);

        $caption = str_repeat('آ', 100);
        $this->putJson("/api/personal-accounts/{$account->id}", [
            'name' => 'کارت اصلی', 'type' => 'card', 'note' => $caption,
        ])->assertOk()->assertJsonPath('note', $caption);

        $this->putJson("/api/personal-accounts/{$account->id}", [
            'note' => str_repeat('ب', 101),
        ])->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    public function test_expense_or_transfer_cannot_make_a_personal_account_negative(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $source = PersonalAccount::create(['user_id' => $user->id, 'name' => 'مبدأ', 'type' => 'card', 'opening_balance' => 100000]);
        $destination = PersonalAccount::create(['user_id' => $user->id, 'name' => 'مقصد', 'type' => 'cash', 'opening_balance' => 0]);

        $payload = [
            'account_id' => $source->id, 'type' => 'expense', 'amount' => 100001,
            'title' => 'هزینه زیاد', 'transaction_date' => today()->toDateString(),
        ];
        $this->postJson('/api/personal-transactions', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'مبلغ هزینه از موجودی حساب انتخاب‌شده بیشتر است');

        $payload['type'] = 'transfer';
        $payload['destination_account_id'] = $destination->id;
        $this->postJson('/api/personal-transactions', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('personal_transactions', 0);
    }

    public function test_reaching_a_savings_goal_creates_only_one_completion_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $goalId = $this->postJson('/api/savings-goals', [
            'title' => 'لپ‌تاپ', 'target_amount' => 1000000, 'saved_amount' => 500000,
        ])->assertCreated()->json('id');

        $this->putJson("/api/savings-goals/{$goalId}", [
            'title' => 'لپ‌تاپ', 'target_amount' => 1000000, 'saved_amount' => 1000000,
        ])->assertOk()->assertJsonPath('is_completed', true);
        $this->putJson("/api/savings-goals/{$goalId}", [
            'title' => 'لپ‌تاپ', 'target_amount' => 1000000, 'saved_amount' => 1100000,
        ])->assertOk();

        $this->assertSame(1, $user->fresh()->notifications()->where('data->event', 'savings_goal_completed')->count());
    }
}
