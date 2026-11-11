<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonalCategoryController extends Controller
{
    private const DEFAULTS = [
        'income' => ['حقوق', 'فروش', 'هدیه', 'سود', 'بازگشت وجه', 'طلب دریافت‌شده', 'سایر'],
        'expense' => ['خوراک', 'خرید', 'مسکن', 'قبوض', 'حمل‌ونقل', 'درمان', 'آموزش', 'تفریح', 'بدهی پرداخت‌شده', 'سایر'],
    ];

    public function index(Request $request)
    {
        $this->initializeFor($request);

        return response()->json($this->grouped($request));
    }

    public function store(Request $request)
    {
        $this->initializeFor($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $data['name'] = trim($data['name']);
        abort_if($data['name'] === '', 422, 'نام دسته‌بندی نمی‌تواند خالی باشد');

        $category = $request->user()->personalCategories()->firstOrCreate($data);

        return response()->json([
            'message' => $category->wasRecentlyCreated ? 'دسته‌بندی اضافه شد' : 'این دسته‌بندی از قبل وجود دارد',
            'category' => $category,
            'categories' => $this->grouped($request),
        ], $category->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, PersonalCategory $personalCategory)
    {
        abort_unless((int) $personalCategory->user_id === (int) $request->user()->id, 403, 'به این دسته‌بندی دسترسی ندارید');
        abort_if(
            $request->user()->personalCategories()->where('type', $personalCategory->type)->count() <= 1,
            422,
            'حداقل یک دسته‌بندی برای این نوع باید باقی بماند'
        );

        $personalCategory->delete();

        return response()->json([
            'message' => 'دسته‌بندی حذف شد؛ تراکنش‌های قبلی بدون تغییر باقی ماندند',
            'categories' => $this->grouped($request),
        ]);
    }

    private function initializeFor(Request $request): void
    {
        $user = $request->user();
        if ($user->personal_categories_initialized_at !== null) {
            return;
        }

        DB::transaction(function () use ($user) {
            $locked = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            if ($locked->personal_categories_initialized_at !== null) {
                return;
            }
            foreach (self::DEFAULTS as $type => $names) {
                foreach ($names as $name) {
                    $locked->personalCategories()->firstOrCreate(compact('type', 'name'));
                }
            }
            $locked->forceFill(['personal_categories_initialized_at' => now()])->save();
            $user->setAttribute('personal_categories_initialized_at', $locked->personal_categories_initialized_at);
        });
    }

    private function grouped(Request $request): array
    {
        $items = $request->user()->personalCategories()->orderBy('id')->get();

        return [
            'income' => $items->where('type', 'income')->values(),
            'expense' => $items->where('type', 'expense')->values(),
        ];
    }
}
