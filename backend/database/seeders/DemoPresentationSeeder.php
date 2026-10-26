<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\Expense;
use App\Models\Group;
use App\Models\PersonalAccount;
use App\Models\PersonalTransaction;
use App\Models\SavingsGoal;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoPresentationSeeder extends Seeder
{
    private const PHONES = ['09120000001', '09120000002', '09120000003', '09120000004', '09120000005'];
    private const REAL_PHONES = ['09940342996', '09057633853'];
    private const INVITES = ['SHM140', 'HOM140', 'BDY140', 'SUB140'];

    public function run(): void
    {
        DB::transaction(function () {
            $this->removePreviousDemoData();

            $users = collect([
                ['name' => 'علی نوری', 'phone' => self::PHONES[0], 'email' => 'ali.demo@example.com'],
                ['name' => 'سارا احمدی', 'phone' => self::PHONES[1], 'email' => 'sara.demo@example.com'],
                ['name' => 'رضا محمدی', 'phone' => self::PHONES[2], 'email' => 'reza.demo@example.com'],
                ['name' => 'نیلوفر کریمی', 'phone' => self::PHONES[3], 'email' => 'niloufar.demo@example.com'],
                ['name' => 'مهدی حسینی', 'phone' => self::PHONES[4], 'email' => 'mehdi.demo@example.com'],
            ])->map(fn (array $data) => User::create([
                ...$data,
                'password' => Hash::make('12345678'),
                'is_completed' => true,
            ]));

            [$ali, $sara, $reza, $niloufar, $mehdi] = $users->all();
            $realUsers = User::whereIn('phone', self::REAL_PHONES)->orderBy('id')->get();
            $firstReal = $realUsers->first();
            $secondReal = $realUsers->skip(1)->first();
            $everyone = $users->concat($realUsers)->unique('id')->values();

            $homeMembers = collect([$ali, $sara, $reza])->when($firstReal, fn ($items) => $items->push($firstReal));
            $birthdayMembers = collect([$ali, $sara, $reza, $mehdi])->when($secondReal, fn ($items) => $items->push($secondReal));
            $subscriptionMembers = collect([$ali, $niloufar, $mehdi])->concat($realUsers)->unique('id')->values();

            $north = $this->group('سفر شمال پاییز', 'dang', $ali, 'SHM140', $everyone);
            $home = $this->group('خانه مشترک سعادت‌آباد', 'account', $sara, 'HOM140', $homeMembers, now()->addDays(25), now()->addDays(20));
            $birthday = $this->group('هدیه تولد نیلوفر', 'dang', $reza, 'BDY140', $birthdayMembers);
            $subscriptions = $this->group('اشتراک‌های ماهانه', 'account', $mehdi, 'SUB140', $subscriptionMembers, now()->addDays(12), now()->addDays(9));

            $this->expense($north, $ali, 4850000, 'اجاره ویلا دو شب', now()->subDays(8), $everyone);
            $this->expense($north, $sara, 1320000, 'خرید مواد غذایی', now()->subDays(7), $everyone);
            $this->expense($north, $reza, 980000, 'بنزین و عوارض جاده', now()->subDays(6), $everyone);
            $this->expense($home, $sara, 2150000, 'قبض برق و گاز', now()->subDays(5), $homeMembers);
            $this->expense($home, $ali, 3600000, 'خرید هفتگی خانه', now()->subDays(3), $homeMembers);
            $this->expense($birthday, $mehdi, 4200000, 'خرید هدیه و کیک', now()->subDays(2), $birthdayMembers);
            $this->expense($subscriptions, $niloufar, 780000, 'اشتراک اینترنت و فیلم', now()->subDay(), $subscriptionMembers);

            Settlement::create([
                'group_id' => $north->id, 'debtor_id' => $mehdi->id, 'creditor_id' => $ali->id,
                'amount' => 700000, 'payment_method' => 'card_to_card', 'status' => 'approved', 'settled_at' => now()->subDays(2),
            ]);
            Settlement::create([
                'group_id' => $home->id, 'debtor_id' => $reza->id, 'creditor_id' => $sara->id,
                'amount' => 450000, 'payment_method' => 'bank_transfer', 'status' => 'pending',
            ]);

            $this->messages($north, [
                [$ali, 'سلام به همه، برنامه سفر نهایی شد 👋', 9],
                [$sara, 'عالیه! لیست خرید رو من آماده می‌کنم.', 8],
                [$reza, 'من ماشین رو قبل حرکت بنزین می‌زنم.', 7],
                [$niloufar, 'ساعت حرکت رو هم اینجا اعلام کنید لطفاً.', 6],
                [$mehdi, 'پنجشنبه ساعت شش صبح مناسبه؟', 5],
                ...($firstReal ? [[$firstReal, 'برای من مناسبه، وسایل ضروری رو هم چک می‌کنم.', 4]] : []),
                ...($secondReal ? [[$secondReal, 'عالیه، من هم آماده‌ام 👍', 3]] : []),
            ]);
            $this->messages($home, [
                [$sara, 'قبض‌های این ماه داخل هزینه‌ها ثبت شد.', 4],
                [$ali, 'ممنون، خرید خانه رو هم من ثبت کردم.', 3],
                [$reza, 'امشب سهم خودم رو تسویه می‌کنم.', 2],
            ]);

            foreach ($users as $index => $user) {
                $main = PersonalAccount::create([
                    'user_id' => $user->id,
                    'name' => $index % 2 === 0 ? 'کارت حقوق' : 'کارت خرید روزانه',
                    'type' => 'card',
                    'opening_balance' => 12000000 + ($index * 1750000),
                    'note' => $index % 2 === 0 ? 'حساب اصلی برای دریافت حقوق' : 'مخصوص خریدهای روزمره',
                ]);
                $cash = PersonalAccount::create([
                    'user_id' => $user->id, 'name' => 'کیف پول نقدی', 'type' => 'cash',
                    'opening_balance' => 850000 + ($index * 100000), 'note' => 'پول نقد همراه',
                ]);
                $this->transaction($user, $main, 'income', 18500000 + ($index * 900000), 'حقوق مهرماه', 'حقوق', now()->subDays(18));
                $this->transaction($user, $main, 'expense', 2350000 + ($index * 120000), 'خرید ماهانه', 'خرید', now()->subDays(12));
                $this->transaction($user, $main, 'expense', 640000 + ($index * 30000), 'قبض‌ها و اینترنت', 'قبوض', now()->subDays(7));
                PersonalTransaction::create([
                    'user_id' => $user->id, 'account_id' => $main->id, 'destination_account_id' => $cash->id,
                    'type' => 'transfer', 'amount' => 1000000, 'title' => 'برداشت برای مخارج نقدی',
                    'transaction_date' => now()->subDays(4)->toDateString(),
                ]);
                $this->transaction($user, $cash, 'expense', 185000 + ($index * 10000), 'رفت‌وآمد شهری', 'حمل‌ونقل', now()->subDays(2));

                SavingsGoal::create([
                    'user_id' => $user->id,
                    'title' => $index % 2 === 0 ? 'خرید لپ‌تاپ جدید' : 'سفر زمستانی',
                    'target_amount' => 45000000,
                    'saved_amount' => 12000000 + ($index * 2500000),
                    'target_date' => now()->addMonths(5)->toDateString(),
                    'is_completed' => false,
                ]);
            }

            $sara->notify(new AppNotification('expense_created', 'علی یک هزینه جدید در «خانه مشترک سعادت‌آباد» ثبت کرد', ['group_id' => $home->id]));
            $ali->notify(new AppNotification('settlement_created', 'رضا درخواست تسویه ۴۵۰٬۰۰۰ تومان را ثبت کرد', ['group_id' => $home->id]));
            $niloufar->notify(new AppNotification('member_joined', 'شما به «سفر شمال پاییز» اضافه شدید', ['group_id' => $north->id]));
        });

        $this->command?->info('Demo presentation data created. Password for all users: 12345678');
    }

    private function group(string $name, string $type, User $creator, string $invite, $members, $due = null, $reminder = null): Group
    {
        $group = Group::create([
            'name' => $name, 'type' => $type, 'creator_id' => $creator->id,
            'invite_code' => $invite, 'require_approval' => true,
            'start_date' => now()->subDays(28), 'due_date' => $due, 'reminder_date' => $reminder,
            'is_active' => true,
        ]);
        $group->members()->attach($members->pluck('id')->mapWithKeys(fn ($id) => [$id => ['joined_at' => now()->subDays(25)]])->all());
        return $group;
    }

    private function expense(Group $group, User $payer, int $amount, string $description, $date, $members): void
    {
        $expense = Expense::create([
            'group_id' => $group->id, 'payer_id' => $payer->id, 'amount' => $amount,
            'description' => $description, 'expense_date' => $date,
        ]);
        $base = intdiv($amount, $members->count());
        $remainder = $amount % $members->count();
        foreach ($members->values() as $index => $member) {
            $expense->splits()->create(['user_id' => $member->id, 'share_amount' => $base + ($index < $remainder ? 1 : 0)]);
        }
    }

    private function messages(Group $group, array $messages): void
    {
        foreach ($messages as [$sender, $text, $hoursAgo]) {
            $message = ChatMessage::create([
                'group_id' => $group->id, 'sender_id' => $sender->id,
                'message' => $text, 'is_seen' => false,
            ]);
            $message->forceFill(['created_at' => now()->subHours($hoursAgo), 'updated_at' => now()->subHours($hoursAgo)])->saveQuietly();
        }
    }

    private function transaction(User $user, PersonalAccount $account, string $type, int $amount, string $title, string $category, $date): void
    {
        PersonalTransaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'type' => $type,
            'amount' => $amount, 'title' => $title, 'category' => $category,
            'transaction_date' => $date->toDateString(),
        ]);
    }

    private function removePreviousDemoData(): void
    {
        Group::whereIn('invite_code', self::INVITES)->get()->each(function (Group $group) {
            foreach ($group->members as $member) {
                $member->notifications()->where('data->group_id', $group->id)->delete();
            }
            ChatMessage::withTrashed()->where('group_id', $group->id)->forceDelete();
            $group->settlements()->delete();
            $group->expenses()->delete();
            $group->members()->detach();
            $group->delete();
        });
        User::whereIn('phone', self::PHONES)->get()->each(function (User $user) {
            $user->notifications()->delete();
            $user->personalTransactions()->delete();
            $user->savingsGoals()->delete();
            $user->personalAccounts()->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
