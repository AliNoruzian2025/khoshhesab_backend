<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->user()->notifications()->latest()->paginate(30)->toArray();
        $page['unread_count'] = $request->user()->unreadNotifications()->count();

        return response()->json($page);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json(['message' => 'اعلان خوانده شد']);
    }
}
