<?php

namespace App\Http\Controllers\Api;

use App\Models\Group;
use App\Models\JoinRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use App\Models\ChatMessage;
use App\Models\GroupDeletionRequest;
use Illuminate\Support\Facades\Notification;

class GroupController extends Controller
{
    // لیست گروه‌های من
    public function index(Request $request)
    {
        Group::where('type', 'account')
            ->where('is_active', true)
            ->whereDate('due_date', '<=', now()->toDateString())
            ->update(['is_active' => false, 'end_date' => now()]);

        $groups = $request->user()->groups()
            ->with('latestMessage.sender')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->latest('groups.created_at')
            ->get();

        $groups->each(function (Group $group) {
            $lastReadId = (int) ($group->pivot->last_read_chat_message_id ?? 0);
            $group->unread_count = $group->messages()
                ->where('id', '>', $lastReadId)
                ->where('sender_id', '!=', request()->user()->id)
                ->count();
        });
        return response()->json($groups);
    }

    // ساخت گروه جدید
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN\s‌_-]+$/u'],
            'type' => 'required|in:dang,account',
            'due_date' => 'required_if:type,account|nullable|date|after_or_equal:today',
            'reminder_date' => 'nullable|date|after_or_equal:today|before:due_date',
            'avatar' => 'nullable|string|max:500',
        ], [
            'name.regex' => 'نام گروه فقط می‌تواند شامل حروف فارسی یا انگلیسی، عدد، فاصله و خط تیره باشد.',
            'due_date.required_if' => 'تاریخ سررسید برای حساب الزامی است.',
            'due_date.after_or_equal' => 'تاریخ سررسید نمی‌تواند قبل از تاریخ شروع حساب باشد.',
            'reminder_date.before' => 'تاریخ یادآوری باید قبل از تاریخ سررسید باشد.',
            'reminder_date.after_or_equal' => 'تاریخ یادآوری نمی‌تواند قبل از امروز باشد.',
        ]);

        $group = Group::create([
            'name' => $request->name,
            'type' => $request->type,
            'creator_id' => $request->user()->id,
            'invite_code' => strtoupper(Str::random(6)),
            'require_approval' => $request->require_approval ?? true,
            'due_date' => $request->due_date,
            'reminder_date' => $request->reminder_date,
            'avatar' => $request->avatar,
            'start_date' => now(),
            'is_active' => true,
        ]);

        // اضافه کردن سازنده به عنوان عضو
        $group->members()->attach($request->user()->id, ['joined_at' => now()]);

        return response()->json($group, 201);
    }

    // نمایش جزئیات گروه
    public function show(Group $group)
    {
        $this->authorize('view', $group);
        if ($group->type === 'account' && $group->is_active && $group->due_date
            && now()->startOfDay()->greaterThanOrEqualTo($group->due_date->copy()->startOfDay())) {
            $group->update(['is_active' => false, 'end_date' => now()]);
        }
        
        $group->load(['members', 'expenses', 'joinRequests.user']);
        $membership = $group->members->firstWhere('id', request()->user()->id)?->pivot;
        $lastReadId = (int) ($membership?->last_read_chat_message_id ?? 0);
        $group->unread_count = $group->messages()
            ->where('id', '>', $lastReadId)
            ->where('sender_id', '!=', request()->user()->id)
            ->count();
        $deletionRequest = $group->deletionRequests()
            ->where('status', 'pending')
            ->with(['requester', 'votes.user'])
            ->latest()
            ->first();
        if ($deletionRequest) {
            $deletionRequest->approved_count = $deletionRequest->votes->where('decision', 'approved')->count();
            $deletionRequest->required_count = $group->members->where('id', '!=', $group->creator_id)->count();
            $deletionRequest->my_vote = $deletionRequest->votes->firstWhere('user_id', request()->user()->id)?->decision;
        }
        $group->deletion_request = $deletionRequest;
        $cutoffDate = $group->type === 'account'
            ? ($group->reminder_date ?: $group->due_date)
            : null;
        $group->expense_entry_closed = $cutoffDate
            ? now()->startOfDay()->greaterThanOrEqualTo($cutoffDate->copy()->startOfDay())
            : false;
        $group->settlement_due = $group->expense_entry_closed;
        $balances = $this->calculateBalances($group);
        $group->all_members_settled = $group->members->count() <= 1 || collect($balances)
            ->every(fn (array $balance) => (int) $balance['balance'] === 0);
        $group->renewal_window_open = $group->type === 'account' && $group->due_date
            ? now()->startOfDay()->greaterThanOrEqualTo($group->due_date->copy()->startOfDay()->subDay())
            : false;
        $group->renewal_available = $group->renewal_window_open && $group->all_members_settled;
        
        return response()->json([
            'group' => $group,
            'balances' => $balances
        ]);
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('manage', $group);

        $effectiveStartDate = $group->start_date?->toDateString() ?: now()->toDateString();
        $minimumDueDate = max($effectiveStartDate, now()->toDateString());
        $effectiveDueDate = $request->input('due_date', $group->due_date?->toDateString());
        $dueDateUnchanged = $request->has('due_date')
            && $request->input('due_date') === $group->due_date?->toDateString();
        $reminderDateUnchanged = $request->has('reminder_date')
            && $request->input('reminder_date') === $group->reminder_date?->toDateString();

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'avatar' => 'sometimes|nullable|string',
            'require_approval' => 'sometimes|boolean',
            'start_date' => 'prohibited',
            'due_date' => $group->type === 'account'
                ? ($dueDateUnchanged ? 'sometimes|required|date' : 'sometimes|required|date|after_or_equal:'.$minimumDueDate)
                : 'sometimes|nullable|date',
            'reminder_date' => 'sometimes|nullable|date'
                .($reminderDateUnchanged ? '' : '|after_or_equal:today')
                .($effectiveDueDate ? '|before:'.$effectiveDueDate : ''),
        ], [
            'due_date.after_or_equal' => 'تاریخ سررسید نمی‌تواند قبل از تاریخ شروع حساب باشد.',
            'reminder_date.before' => 'تاریخ یادآوری باید قبل از تاریخ سررسید باشد.',
            'reminder_date.after_or_equal' => 'تاریخ یادآوری نمی‌تواند قبل از امروز باشد.',
        ]);

        $group->update($request->only([
            'name', 'avatar', 'require_approval', 'due_date', 'reminder_date',
        ]));

        return response()->json($group->fresh());
    }

    // پیوستن به گروه با کد دعوت
    public function join(Request $request)
    {
        $request->validate(['invite_code' => 'required|string|size:6']);
        
        $group = Group::where('invite_code', $request->invite_code)->first();
        if (!$group) {
            return response()->json(['message' => 'کد دعوت نامعتبر است'], 404);
        }
        if (! $group->is_active) {
            return response()->json(['message' => 'این گروه غیرفعال است'], 422);
        }
        
        // چک کن کاربر قبلاً عضو نیست
        if ($group->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'شما قبلاً عضو این گروه هستید'], 422);
        }
        
        if ($group->require_approval) {
            // نیاز به تأیید
            $joinRequest = JoinRequest::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $request->user()->id],
                ['status' => 'pending']
            );
            $group->creator->notify(new AppNotification(
                'join_request_created',
                ($request->user()->name ?: $request->user()->phone).' درخواست عضویت در «'.$group->name.'» را ثبت کرد',
                [
                    'group_id' => $group->id,
                    'join_request_id' => $joinRequest->id,
                    'user_id' => $request->user()->id,
                ],
            ));
            return response()->json(['message' => 'درخواست عضویت ارسال شد. منتظر تأیید سازنده باشید.']);
        } else {
            // بدون نیاز به تأیید
            $group->members()->attach($request->user()->id, ['joined_at' => now()]);
            \Illuminate\Support\Facades\Notification::send(
                $group->members,
                new AppNotification(
                    'member_joined',
                    ($request->user()->name ?: $request->user()->phone).' عضو «'.$group->name.'» شد',
                    ['group_id' => $group->id, 'user_id' => $request->user()->id],
                )
            );
            return response()->json(['message' => 'عضویت موفق']);
        }
    }

    // تأیید یا رد درخواست عضویت (فقط سازنده)
    public function handleJoinRequest(Request $request, Group $group, JoinRequest $joinRequest)
    {
        $this->authorize('manage', $group);

        if ($joinRequest->group_id !== $group->id || $joinRequest->status !== 'pending') {
            return response()->json(['message' => 'درخواست عضویت نامعتبر است'], 404);
        }
        
        $request->validate(['status' => 'required|in:approved,rejected']);
        
        $joinRequest->update(['status' => $request->status]);

        $group->creator->notifications()
            ->where('data->event', 'join_request_created')
            ->where('data->join_request_id', $joinRequest->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        
        if ($request->status == 'approved') {
            $group->members()->syncWithoutDetaching([
                $joinRequest->user_id => ['joined_at' => now()],
            ]);
        }

        $recipients = $group->members->push($joinRequest->user)->unique('id');
        \Illuminate\Support\Facades\Notification::send($recipients, new AppNotification(
            'join_request_'.$request->status,
            $request->status === 'approved'
                ? ($joinRequest->user->name ?: 'کاربر').' عضو «'.$group->name.'» شد'
                : 'درخواست عضویت '.($joinRequest->user->name ?: 'کاربر').' در «'.$group->name.'» رد شد',
            ['group_id' => $group->id, 'join_request_id' => $joinRequest->id],
        ));
        
        return response()->json(['message' => 'درخواست با موفقیت ' . ($request->status == 'approved' ? 'تأیید' : 'رد') . ' شد']);
    }

    // حذف عضو از گروه (فقط سازنده)
    public function removeMember(Group $group, User $user)
    {
        $this->authorize('manage', $group);

        if (! $group->members()->whereKey($user->id)->exists()) {
            return response()->json(['message' => 'کاربر عضو این گروه نیست'], 404);
        }
        
        if ($group->creator_id == $user->id) {
            return response()->json(['message' => 'سازنده گروه را نمی‌توان حذف کرد'], 422);
        }
        
        $group->members()->detach($user->id);
        
        return response()->json(['message' => 'عضو با موفقیت حذف شد']);
    }

    // خروج از گروه
    public function leave(Group $group, Request $request)
    {
        $this->authorize('view', $group);
        if ($group->creator_id == $request->user()->id) {
            return response()->json(['message' => 'سازنده گروه نمی‌تواند خارج شود. ابتدا گروه را حذف کنید.'], 422);
        }
        
        $group->members()->detach($request->user()->id);
        
        return response()->json(['message' => 'با موفقیت خارج شدید']);
    }

    // حذف گروه (فقط سازنده)
    public function destroy(Request $request, Group $group)
    {
        $this->authorize('manage', $group);

        $otherMembers = $group->members()->where('users.id', '!=', $group->creator_id)->get();
        if ($group->expenses()->doesntExist() || $otherMembers->isEmpty()) {
            $this->forceDeleteGroup($group);
            return response()->json([
                'message' => 'دنگ یا حساب با موفقیت حذف شد',
                'group_deleted' => true,
            ]);
        }

        $deletionRequest = GroupDeletionRequest::firstOrCreate([
            'group_id' => $group->id,
            'status' => 'pending',
        ], [
            'requester_id' => $request->user()->id,
        ]);

        if ($deletionRequest->wasRecentlyCreated) {
            $message = ($request->user()->name ?: 'سازنده').' درخواست حذف «'.$group->name.'» را ثبت کرده است. حذف فقط با تأیید همه اعضا انجام می‌شود.';
            Notification::send($otherMembers, new AppNotification(
                'group_deletion_requested',
                $message.' آیا با حذف موافق هستید؟',
                ['group_id' => $group->id, 'deletion_request_id' => $deletionRequest->id],
            ));
            ChatMessage::create([
                'group_id' => $group->id,
                'sender_id' => $request->user()->id,
                'message' => $message,
                'is_system' => true,
                'is_seen' => false,
            ]);
        }

        return response()->json([
            'message' => 'درخواست حذف برای اعضا ارسال شد و پس از تأیید همه انجام می‌شود',
            'requires_approval' => true,
            'deletion_request_id' => $deletionRequest->id,
        ], 202);
    }

    public function voteDeletion(Request $request, Group $group, GroupDeletionRequest $deletionRequest)
    {
        $this->authorize('view', $group);
        if ($deletionRequest->group_id !== $group->id || $deletionRequest->status !== 'pending') {
            return response()->json(['message' => 'درخواست حذف فعال نیست'], 422);
        }
        if ((int) $request->user()->id === (int) $group->creator_id) {
            return response()->json(['message' => 'سازنده در رأی اعضا شرکت نمی‌کند'], 422);
        }
        $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        $deletionRequest->votes()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['decision' => $request->decision],
        );
        $request->user()->notifications()
            ->where('data->event', 'group_deletion_requested')
            ->where('data->deletion_request_id', $deletionRequest->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->decision === 'rejected') {
            $deletionRequest->update(['status' => 'rejected', 'resolved_at' => now()]);
            $this->markDeletionNotificationsRead($group, $deletionRequest->id);
            $result = ($request->user()->name ?: 'یکی از اعضا').' با حذف «'.$group->name.'» مخالفت کرد؛ درخواست حذف لغو شد.';
            Notification::send($group->members, new AppNotification(
                'group_deletion_rejected', $result,
                ['group_id' => $group->id, 'deletion_request_id' => $deletionRequest->id],
            ));
            ChatMessage::create([
                'group_id' => $group->id,
                'sender_id' => $group->creator_id,
                'message' => $result,
                'is_system' => true,
                'is_seen' => false,
            ]);
            return response()->json(['message' => 'مخالفت شما ثبت شد و حذف لغو شد']);
        }

        $requiredIds = $group->members()->where('users.id', '!=', $group->creator_id)->pluck('users.id');
        $approvedIds = $deletionRequest->votes()->where('decision', 'approved')->pluck('user_id');
        if ($requiredIds->diff($approvedIds)->isEmpty()) {
            $deletionRequest->update(['status' => 'approved', 'resolved_at' => now()]);
            $this->markDeletionNotificationsRead($group, $deletionRequest->id);
            Notification::send($group->members, new AppNotification(
                'group_deletion_approved',
                'همه اعضا با حذف «'.$group->name.'» موافقت کردند و این مورد حذف شد.',
                ['group_id' => $group->id, 'deletion_request_id' => $deletionRequest->id],
            ));
            $this->forceDeleteGroup($group);
            return response()->json(['message' => 'تأیید شما ثبت شد؛ همه اعضا موافقت کردند و حذف انجام شد', 'group_deleted' => true]);
        }

        return response()->json(['message' => 'تأیید شما ثبت شد؛ منتظر رأی سایر اعضا هستیم']);
    }

    public function renewAccount(Request $request, Group $group)
    {
        $this->authorize('manage', $group);

        if ($group->type !== 'account') {
            return response()->json(['message' => 'فقط حساب‌های بلندمدت قابل تمدید هستند'], 422);
        }

        if (! $group->due_date || now()->startOfDay()->lt($group->due_date->copy()->startOfDay()->subDay())) {
            return response()->json(['message' => 'تمدید حساب فقط از یک روز مانده به سررسید فعال می‌شود'], 422);
        }

        if (collect($this->calculateBalances($group))->contains(
            fn (array $balance) => (int) $balance['balance'] !== 0
        )) {
            return response()->json([
                'message' => 'تمدید حساب پس از تسویه کامل مانده همه اعضا امکان‌پذیر است',
            ], 422);
        }

        $request->validate([
            'due_date' => ['required', 'date', 'after:'.$group->due_date->toDateString()],
            'reminder_date' => ['nullable', 'date', 'after_or_equal:'.$group->due_date->toDateString(), 'before:due_date'],
        ], [
            'due_date.required' => 'سررسید دوره جدید الزامی است.',
            'due_date.after' => 'سررسید جدید باید بعد از سررسید فعلی باشد.',
            'reminder_date.before' => 'یادآور باید قبل از سررسید جدید باشد.',
        ]);

        $newGroup = DB::transaction(function () use ($request, $group) {
            $group->update(['is_active' => false, 'end_date' => now()]);

            $newGroup = Group::create([
                'name' => $group->name,
                'type' => 'account',
                'avatar' => $group->avatar,
                'creator_id' => $group->creator_id,
                'invite_code' => strtoupper(Str::random(6)),
                'require_approval' => $group->require_approval,
                'due_date' => $request->due_date,
                'reminder_date' => $request->reminder_date,
                'start_date' => now(),
                'is_active' => true,
                'parent_group_id' => $group->id,
            ]);

            $members = $group->members()->pluck('users.id')
                ->mapWithKeys(fn ($id) => [$id => ['joined_at' => now()]])
                ->all();
            $newGroup->members()->attach($members);

            return $newGroup;
        });

        return response()->json([
            'message' => 'حساب تمدید شد و چت دوره قبلی آرشیو شد',
            'group' => $newGroup,
            'archived_group_id' => $group->id,
        ], 201);
    }

    public function toggleDangActive(Group $group)
    {
        $this->authorize('manage', $group);

        if ($group->type !== 'dang') {
            return response()->json(['message' => 'فعال‌سازی دستی فقط برای دنگ امکان‌پذیر است'], 422);
        }

        $group->update([
            'is_active' => ! $group->is_active,
            'end_date' => $group->is_active ? now() : null,
        ]);

        return response()->json([
            'message' => $group->is_active ? 'دنگ دوباره فعال شد' : 'دنگ غیرفعال شد',
            'group' => $group->fresh(),
        ]);
    }

    private function forceDeleteGroup(Group $group): void
    {
        DB::transaction(function () use ($group) {
            ChatMessage::withTrashed()->where('group_id', $group->id)->update(['reply_to_id' => null]);
            ChatMessage::withTrashed()->where('group_id', $group->id)->forceDelete();
            $group->delete();
        });
    }

    private function markDeletionNotificationsRead(Group $group, int $deletionRequestId): void
    {
        $group->members->each(function (User $member) use ($deletionRequestId) {
            $member->notifications()
                ->where('data->event', 'group_deletion_requested')
                ->where('data->deletion_request_id', $deletionRequestId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        });
    }

    // محاسبه بدهکاری اعضا
    private function calculateBalances($group)
    {
        $expenses = $group->expenses()->with('splits')->get();
        $balances = [];
        
        foreach ($expenses as $expense) {
            // پرداخت‌کننده طلبکار می‌شود
            $balances[$expense->payer_id] = ($balances[$expense->payer_id] ?? 0) + $expense->amount;
            
            // هر کس سهم دارد، بدهکار می‌شود
            foreach ($expense->splits as $split) {
                $balances[$split->user_id] = ($balances[$split->user_id] ?? 0) - $split->share_amount;
            }
        }

        foreach ($group->settlements()->where('status', 'approved')->get() as $settlement) {
            $balances[$settlement->debtor_id] = ($balances[$settlement->debtor_id] ?? 0) + $settlement->amount;
            $balances[$settlement->creditor_id] = ($balances[$settlement->creditor_id] ?? 0) - $settlement->amount;
        }
        
        // تبدیل به فرمت مناسب
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
