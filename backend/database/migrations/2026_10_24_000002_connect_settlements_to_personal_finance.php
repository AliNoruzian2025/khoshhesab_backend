<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card_to_card', 'personal_account', 'other'])->default('other')->after('amount');
            $table->foreignId('debtor_personal_account_id')->nullable()->after('payment_method')->constrained('personal_accounts')->nullOnDelete();
            $table->foreignId('creditor_personal_account_id')->nullable()->after('debtor_personal_account_id')->constrained('personal_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creditor_personal_account_id');
            $table->dropConstrainedForeignId('debtor_personal_account_id');
            $table->dropColumn('payment_method');
        });
    }
};
