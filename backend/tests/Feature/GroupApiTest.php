<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Models\Expense;
use App\Models\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_open_group_details_without_server_error(): void
    {
        $user = User::factory()->create(['phone' => '09121111111']);
        $group = Group::create([
            'name' => 'دنگ تست',
            'type' => 'dang',
            'creator_id' => $user->id,
            'invite_code' => 'ABC123',
            'start_date' => now(),
            'is_active' => true,
        ]);
        $group->members()->attach($user->id, ['joined_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.name', 'دنگ تست')
            ->assertJsonStructure(['group', 'balances']);
    }

    public function test_android_create_group_payload_is_accepted(): void
    {
        $user = User::factory()->create(['phone' => '09122222222']);
        Sanctum::actingAs($user);

        $this->postJson('/api/groups', [
            'name' => 'حساب خانه ۱۴۰۵',
            'type' => 'account',
            'due_date' => now()->addMonth()->toDateString(),
            'reminder_date' => null,
            'require_approval' => true,
        ])->assertCreated()
            ->assertJsonPath('type', 'account')
            ->assertJsonPath('name', 'حساب خانه ۱۴۰۵');
    }

    public function test_account_requires_a_due_date(): void
    {
        $user = User::factory()->create(['phone' => '09123333333']);
        Sanctum::actingAs($user);

        $this->postJson('/api/groups', [
            'name' => 'حساب بدون سررسید',
            'type' => 'account',
            'due_date' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_account_dates_follow_start_and_due_date_order(): void
    {
        $user = User::factory()->create(['phone' => '09123333334']);
        Sanctum::actingAs($user);

        $this->postJson('/api/groups', [
            'name' => 'حساب با سررسید گذشته',
            'type' => 'account',
            'due_date' => now()->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('due_date');

        $dueDate = now()->addWeek()->toDateString();
        $this->postJson('/api/groups', [
            'name' => 'حساب با یادآور نامعتبر',
            'type' => 'account',
            'due_date' => $dueDate,
            'reminder_date' => $dueDate,
        ])->assertUnprocessable()->assertJsonValidationErrors('reminder_date');
    }

    public function test_member_can_send_a_receipt_only_chat_message(): void
    {
        $user = User::factory()->create(['phone' => '09124444444']);
        $group = Group::create([
            'name' => 'چت رسید',
            'type' => 'dang',
            'creator_id' => $user->id,
            'invite_code' => 'RCT123',
            'start_date' => now(),
            'is_active' => true,
        ]);
        $group->members()->attach($user->id, ['joined_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson("/api/groups/{$group->id}/messages", [
            'receipt_image' => 'storage/receipts/example.jpg',
        ])->assertCreated()
            ->assertJsonPath('receipt_image', 'storage/receipts/example.jpg');
    }

    public function test_join_request_notifies_group_creator(): void
    {
        $creator = User::factory()->create(['phone' => '09125555555']);
        $applicant = User::factory()->create(['phone' => '09126666666', 'name' => 'عضو جدید']);
        $group = Group::create([
            'name' => 'گروه اعلان',
            'type' => 'dang',
            'creator_id' => $creator->id,
            'invite_code' => 'NOT123',
            'require_approval' => true,
            'start_date' => now(),
            'is_active' => true,
        ]);
        $group->members()->attach($creator->id, ['joined_at' => now()]);
        Sanctum::actingAs($applicant);

        $this->postJson('/api/groups/join', ['invite_code' => 'NOT123'])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $creator->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_chat_unread_count_is_per_member_and_clears_when_opened(): void
    {
        $sender = User::factory()->create(['phone' => '09127777777']);
        $reader = User::factory()->create(['phone' => '09128888888']);
        $group = Group::create([
            'name' => 'چت خوانده نشده',
            'type' => 'dang',
            'creator_id' => $sender->id,
            'invite_code' => 'UNR123',
            'start_date' => now(),
            'is_active' => true,
        ]);
        $group->members()->attach([$sender->id, $reader->id]);

        Sanctum::actingAs($sender);
        $this->postJson("/api/groups/{$group->id}/messages", ['message' => 'سلام'])->assertCreated();

        Sanctum::actingAs($reader);
        $this->getJson('/api/groups')->assertJsonPath('0.unread_count', 1);
        $this->getJson("/api/groups/{$group->id}/messages")->assertOk();
        $this->getJson('/api/groups')->assertJsonPath('0.unread_count', 0);
    }

    public function test_chat_message_owner_can_edit_text_and_delete_but_other_member_cannot(): void
    {
        $owner = User::factory()->create(['phone' => '09129999991']);
        $other = User::factory()->create(['phone' => '09129999992']);
        $group = Group::create([
            'name' => 'عملیات پیام',
            'type' => 'dang',
            'creator_id' => $owner->id,
            'invite_code' => 'EDT123',
            'start_date' => now(),
            'is_active' => true,
        ]);
        $group->members()->attach([$owner->id, $other->id]);

        Sanctum::actingAs($owner);
        $messageId = $this->postJson("/api/groups/{$group->id}/messages", [
            'message' => 'متن اولیه',
        ])->assertCreated()->json('id');

        Sanctum::actingAs($other);
        $this->patchJson("/api/messages/{$messageId}", ['message' => 'دست‌کاری'])
            ->assertForbidden();
        $this->deleteJson("/api/messages/{$messageId}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/messages/{$messageId}", ['message' => 'متن ویرایش‌شده'])
            ->assertOk()
            ->assertJsonPath('message', 'متن ویرایش‌شده')
            ->assertJsonPath('edited_at', fn ($value) => $value !== null);
        $this->deleteJson("/api/messages/{$messageId}")->assertOk();

        Sanctum::actingAs($other);
        $this->getJson("/api/groups/{$group->id}/messages")
            ->assertOk()
            ->assertJsonPath('0.message', null)
            ->assertJsonPath('0.deleted_at', fn ($value) => $value !== null);
    }

    public function test_only_creator_can_edit_or_delete_group(): void
    {
        $creator = User::factory()->create(['phone' => '09129999993']);
        $member = User::factory()->create(['phone' => '09129999994']);
        $group = Group::create([
            'name' => 'نام قدیمی',
            'type' => 'account',
            'creator_id' => $creator->id,
            'invite_code' => 'MNG123',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);
        $group->members()->attach([$creator->id, $member->id]);

        Sanctum::actingAs($member);
        $this->putJson("/api/groups/{$group->id}", [
            'name' => 'دست‌کاری عضو',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeeks(2)->toDateString(),
        ])->assertForbidden();
        $this->deleteJson("/api/groups/{$group->id}")->assertForbidden();

        Sanctum::actingAs($creator);
        $this->putJson("/api/groups/{$group->id}", [
            'name' => 'نام جدید حساب',
            'due_date' => now()->addWeeks(2)->toDateString(),
            'reminder_date' => now()->addWeek()->toDateString(),
        ])->assertOk()->assertJsonPath('name', 'نام جدید حساب');

        $this->deleteJson("/api/groups/{$group->id}")->assertOk();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_with_members_but_without_expenses_is_deleted_immediately(): void
    {
        $creator = User::factory()->create(['phone' => '09129999995']);
        $member = User::factory()->create(['phone' => '09129999996']);
        $group = Group::create([
            'name' => 'بدون هزینه', 'type' => 'dang', 'creator_id' => $creator->id,
            'invite_code' => 'DEL124', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$creator->id, $member->id]);
        Sanctum::actingAs($creator);

        $this->deleteJson("/api/groups/{$group->id}")
            ->assertOk()->assertJsonPath('group_deleted', true);
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_with_expense_requires_all_other_members_to_approve_deletion(): void
    {
        $creator = User::factory()->create(['phone' => '09129999997', 'name' => 'علی']);
        $member = User::factory()->create(['phone' => '09129999998', 'name' => 'رضا']);
        $group = Group::create([
            'name' => 'هم‌خانه', 'type' => 'dang', 'creator_id' => $creator->id,
            'invite_code' => 'DEL125', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$creator->id, $member->id]);
        Expense::create([
            'group_id' => $group->id, 'payer_id' => $creator->id, 'amount' => 1000,
            'description' => 'هزینه تست', 'expense_date' => now(),
        ]);
        Sanctum::actingAs($creator);
        $requestId = $this->deleteJson("/api/groups/{$group->id}")
            ->assertStatus(202)
            ->assertJsonPath('requires_approval', true)
            ->json('deletion_request_id');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id]);
        $this->assertDatabaseHas('chat_messages', ['group_id' => $group->id, 'is_system' => true]);
        $this->assertDatabaseHas('groups', ['id' => $group->id]);

        Sanctum::actingAs($member);
        $this->postJson("/api/groups/{$group->id}/deletion-request/{$requestId}/vote", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('group_deleted', true);
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_one_rejection_cancels_group_deletion(): void
    {
        $creator = User::factory()->create(['phone' => '09129999999']);
        $member = User::factory()->create(['phone' => '09121111999']);
        $group = Group::create([
            'name' => 'گروه باقی‌مانده', 'type' => 'dang', 'creator_id' => $creator->id,
            'invite_code' => 'DEL126', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$creator->id, $member->id]);
        Expense::create([
            'group_id' => $group->id, 'payer_id' => $creator->id, 'amount' => 1000,
            'description' => 'هزینه تست', 'expense_date' => now(),
        ]);
        Sanctum::actingAs($creator);
        $requestId = $this->deleteJson("/api/groups/{$group->id}")->json('deletion_request_id');

        Sanctum::actingAs($member);
        $this->postJson("/api/groups/{$group->id}/deletion-request/{$requestId}/vote", [
            'decision' => 'rejected',
        ])->assertOk();
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
        $this->assertDatabaseHas('group_deletion_requests', [
            'id' => $requestId, 'status' => 'rejected',
        ]);
    }

    public function test_account_start_date_cannot_be_changed_and_reminder_is_optional(): void
    {
        $creator = User::factory()->create(['phone' => '09121111888']);
        $group = Group::create([
            'name' => 'حساب تاریخ', 'type' => 'account', 'creator_id' => $creator->id,
            'invite_code' => 'DAT123', 'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(), 'is_active' => true,
        ]);
        $group->members()->attach($creator->id);
        Sanctum::actingAs($creator);

        $this->putJson("/api/groups/{$group->id}", [
            'name' => 'حساب تاریخ',
            'start_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->addWeeks(2)->toDateString(),
            'reminder_date' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->putJson("/api/groups/{$group->id}", [
            'name' => 'حساب بدون یادآور',
            'due_date' => now()->addWeeks(2)->toDateString(),
            'reminder_date' => null,
        ])->assertOk()->assertJsonPath('reminder_date', null);
    }

    public function test_expense_entry_closes_when_account_settlement_date_arrives(): void
    {
        $creator = User::factory()->create(['phone' => '09121111777']);
        $group = Group::create([
            'name' => 'حساب موعد رسیده', 'type' => 'account', 'creator_id' => $creator->id,
            'invite_code' => 'DUE123', 'start_date' => now()->subMonth(),
            'due_date' => now()->addWeek(), 'reminder_date' => today(), 'is_active' => true,
        ]);
        $group->members()->attach($creator->id);
        Sanctum::actingAs($creator);

        $this->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.expense_entry_closed', true)
            ->assertJsonPath('group.settlement_due', true);
        $this->postJson("/api/groups/{$group->id}/expenses", [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'موعد تسویه حساب فرا رسیده و ثبت هزینه جدید بسته شده است');
    }

    public function test_account_renewal_only_opens_from_one_day_before_due_date(): void
    {
        $creator = User::factory()->create(['phone' => '09121111666']);
        $group = Group::create([
            'name' => 'حساب دور', 'type' => 'account', 'creator_id' => $creator->id,
            'invite_code' => 'REN123', 'start_date' => now(),
            'due_date' => now()->addWeek(), 'is_active' => true,
        ]);
        $group->members()->attach($creator->id);
        Sanctum::actingAs($creator);

        $this->postJson("/api/groups/{$group->id}/renew", [
            'due_date' => now()->addMonth()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_account_becomes_inactive_on_due_date(): void
    {
        $creator = User::factory()->create(['phone' => '09121111555']);
        $group = Group::create([
            'name' => 'حساب سررسیدشده', 'type' => 'account', 'creator_id' => $creator->id,
            'invite_code' => 'INA123', 'start_date' => now()->subMonth(),
            'due_date' => today(), 'is_active' => true,
        ]);
        $group->members()->attach($creator->id);
        Sanctum::actingAs($creator);

        $this->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.is_active', false);
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'is_active' => false]);
    }

    public function test_creator_can_deactivate_and_reactivate_dang_but_member_cannot(): void
    {
        $creator = User::factory()->create(['phone' => '09121111554']);
        $member = User::factory()->create(['phone' => '09121111553']);
        $group = Group::create([
            'name' => 'دنگ رفت و برگشت', 'type' => 'dang', 'creator_id' => $creator->id,
            'invite_code' => 'TGL123', 'start_date' => now(), 'is_active' => true,
        ]);
        $group->members()->attach([$creator->id, $member->id]);

        Sanctum::actingAs($member);
        $this->postJson("/api/groups/{$group->id}/toggle-active")->assertForbidden();

        Sanctum::actingAs($creator);
        $this->postJson("/api/groups/{$group->id}/toggle-active")
            ->assertOk()->assertJsonPath('group.is_active', false);
        $this->postJson("/api/groups/{$group->id}/expenses", [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'این دنگ یا حساب غیرفعال است و هزینه جدید نمی‌پذیرد');
        $this->postJson("/api/groups/{$group->id}/toggle-active")
            ->assertOk()->assertJsonPath('group.is_active', true);
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'is_active' => true, 'end_date' => null]);
    }

    public function test_account_can_only_be_renewed_after_all_balances_are_settled(): void
    {
        $creator = User::factory()->create(['phone' => '09121111552']);
        $member = User::factory()->create(['phone' => '09121111551']);
        $group = Group::create([
            'name' => 'حساب قابل تمدید', 'type' => 'account', 'creator_id' => $creator->id,
            'invite_code' => 'SET123', 'start_date' => now()->subMonth(),
            'due_date' => today(), 'is_active' => false,
        ]);
        $group->members()->attach([$creator->id, $member->id]);
        $expense = Expense::create([
            'group_id' => $group->id, 'payer_id' => $creator->id, 'amount' => 1000,
            'description' => 'قبض', 'expense_date' => now(),
        ]);
        $expense->splits()->create(['user_id' => $member->id, 'share_amount' => 1000]);
        Sanctum::actingAs($creator);

        $payload = ['due_date' => now()->addMonth()->toDateString(), 'reminder_date' => null];
        $this->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.renewal_window_open', true)
            ->assertJsonPath('group.all_members_settled', false)
            ->assertJsonPath('group.renewal_available', false);
        $this->postJson("/api/groups/{$group->id}/renew", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'تمدید حساب پس از تسویه کامل مانده همه اعضا امکان‌پذیر است');

        Settlement::create([
            'group_id' => $group->id, 'debtor_id' => $member->id,
            'creditor_id' => $creator->id, 'amount' => 1000,
            'status' => 'approved', 'settled_at' => now(),
        ]);
        $this->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.all_members_settled', true)
            ->assertJsonPath('group.renewal_available', true);
        $this->postJson("/api/groups/{$group->id}/renew", $payload)
            ->assertCreated()
            ->assertJsonPath('group.is_active', true)
            ->assertJsonPath('group.parent_group_id', $group->id);
    }
}
