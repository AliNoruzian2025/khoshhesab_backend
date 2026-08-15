<?php

use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/server-time', function () {
    $now = now('Asia/Tehran');

    return response()->json([
        'date' => $now->toDateString(),
        'datetime' => $now->toIso8601String(),
        'timezone' => 'Asia/Tehran',
    ]);
});

// ===== روت‌های عمومی (بدون توکن) =====
Route::post('/send-otp', [PhoneAuthController::class, 'sendOtp'])->middleware('throttle:6,1');
Route::post('/verify-otp', [PhoneAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::get('/otp/status', [PhoneAuthController::class, 'otpStatus'])->middleware('throttle:30,1');
Route::post('/login', [PhoneAuthController::class, 'loginWithPassword'])->middleware('throttle:10,1');
Route::post('/register', [PhoneAuthController::class, 'register']);
Route::post('/reset-password', [PhoneAuthController::class, 'resetPassword']);
Route::post('/check-user', [PhoneAuthController::class, 'checkUserExists']);

// ===== روت لاگین برای جلوگیری از خطای auth:sanctum =====
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated'], 401);
})->name('login');

// ===== روت‌های محافظت شده (نیاز به توکن) =====
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [PhoneAuthController::class, 'logout']);
    
    // پروفایل
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/profile/change-phone', [ProfileController::class, 'requestPhoneChange']);
    Route::post('/profile/verify-phone', [ProfileController::class, 'verifyPhoneChange']);
    
    // گروه‌ها
    Route::apiResource('groups', GroupController::class);
    Route::post('/groups/join', [GroupController::class, 'join']);
    Route::post('/groups/{group}/join-request/{joinRequest}', [GroupController::class, 'handleJoinRequest']);
    Route::post('/groups/{group}/deletion-request/{deletionRequest}/vote', [GroupController::class, 'voteDeletion']);
    Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave']);
    Route::post('/groups/{group}/renew', [GroupController::class, 'renewAccount']);
    Route::post('/groups/{group}/toggle-active', [GroupController::class, 'toggleDangActive']);
    
    // دنگ‌ها (Trip)
    Route::prefix('trips')->group(function () {
        Route::get('/', [TripController::class, 'index']);
        Route::post('/', [TripController::class, 'store']);
        Route::post('/{trip}/end', [TripController::class, 'end']);
        Route::post('/{trip}/renew', [TripController::class, 'renew']);
    });
    
    // هزینه‌ها
    Route::get('/groups/{group}/expenses', [ExpenseController::class, 'index']);
    Route::post('/groups/{group}/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    
    // تسویه‌ها
    Route::get('/groups/{group}/settlements', [SettlementController::class, 'index']);
    Route::post('/groups/{group}/settlements', [SettlementController::class, 'store']);
    Route::post('/settlements/{settlement}/approve', [SettlementController::class, 'approve']);
    Route::post('/settlements/{settlement}/reject', [SettlementController::class, 'reject']);
    
    // چت
    Route::get('/groups/{group}/messages', [ChatController::class, 'index']);
    Route::post('/groups/{group}/messages', [ChatController::class, 'store']);
    Route::patch('/messages/{message}', [ChatController::class, 'update']);
    Route::delete('/messages/{message}', [ChatController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/uploads/receipt', [UploadController::class, 'receipt']);
    Route::post('/uploads/group-image', [UploadController::class, 'groupImage']);
});
