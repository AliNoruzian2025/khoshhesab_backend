<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatMessage;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ChatController extends Controller
{
    // دریافت تاریخچه پیام‌های یک گروه
    public function index(Group $group)
    {
        $this->authorize('view', $group);
        
        $messages = $group->messages()
            ->withTrashed()
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'asc')
            ->get();

        $group->members()->updateExistingPivot(request()->user()->id, [
            'last_read_chat_message_id' => $messages->last()?->id,
        ]);
        
        return response()->json($messages);
    }

    // ارسال پیام جدید
    public function store(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        if (! $group->is_active) {
            return response()->json(['message' => 'چت این گروه بسته شده است'], 422);
        }
        
        $request->validate([
            'message' => 'required_without_all:sticker,receipt_image|string|nullable',
            'sticker' => 'required_without_all:message,receipt_image|string|nullable',
            'reply_to_id' => 'nullable|exists:chat_messages,id',
            'receipt_image' => 'required_without_all:message,sticker|string|nullable',
        ]);

        if ($request->reply_to_id && ! $group->messages()->whereKey($request->reply_to_id)->exists()) {
            return response()->json(['message' => 'پیام ریپلای متعلق به این گروه نیست'], 422);
        }
        
        $message = ChatMessage::create([
            'group_id' => $group->id,
            'sender_id' => $request->user()->id,
            'message' => $request->message,
            'reply_to_id' => $request->reply_to_id,
            'sticker' => $request->sticker,
            'receipt_image' => $request->receipt_image,
            'is_seen' => false,
        ]);
        
        return response()->json($message->load(['sender', 'replyTo.sender']), 201);
    }

    public function update(Request $request, ChatMessage $message)
    {
        $this->authorize('view', $message->group);
        if ($message->is_system) {
            return response()->json(['message' => 'پیام سیستمی قابل ویرایش نیست'], 422);
        }
        if ((int) $message->sender_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'فقط فرستنده می‌تواند پیام را ویرایش کند'], 403);
        }
        if ($message->receipt_image || $message->sticker || blank($message->message)) {
            return response()->json(['message' => 'فقط پیام متنی قابل ویرایش است'], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);
        $message->update([
            'message' => trim($validated['message']),
            'edited_at' => now(),
        ]);

        return response()->json($message->fresh()->load(['sender', 'replyTo.sender']));
    }

    public function destroy(Request $request, ChatMessage $message)
    {
        $this->authorize('view', $message->group);
        if ($message->is_system) {
            return response()->json(['message' => 'پیام سیستمی قابل حذف نیست'], 422);
        }
        if ((int) $message->sender_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'فقط فرستنده می‌تواند پیام را حذف کند'], 403);
        }

        $message->update([
            'message' => null,
            'sticker' => null,
            'receipt_image' => null,
        ]);
        $message->delete();

        return response()->json(['message' => 'پیام حذف شد']);
    }
}
