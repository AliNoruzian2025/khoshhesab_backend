<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function receipt(Request $request)
    {
        $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('receipt')->store('receipts', 'public');

        return response()->json([
            'message' => 'تصویر با موفقیت بارگذاری شد',
            'path' => 'storage/'.$path,
        ], 201);
    }

    public function groupImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('image')->store('group-images', 'public');

        return response()->json([
            'message' => 'تصویر دنگ یا حساب با موفقیت بارگذاری شد',
            'path' => 'storage/'.$path,
        ], 201);
    }
}
