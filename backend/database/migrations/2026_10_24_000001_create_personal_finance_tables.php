<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['bank', 'card', 'cash', 'wallet', 'other']);
            $table->decimal('opening_balance', 15, 0)->default(0);
            $table->string('color', 20)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_archived']);
        });

        Schema::create('personal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('personal_accounts')->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('personal_accounts')->restrictOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer', 'adjustment']);
            $table->decimal('amount', 15, 0);
            $table->string('category', 80)->nullable();
            $table->string('title', 150);
            $table->text('note')->nullable();
            $table->date('transaction_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
            $table->unique(['user_id', 'reference_type', 'reference_id'], 'personal_tx_reference_unique');
        });

        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->decimal('target_amount', 15, 0);
            $table->decimal('saved_amount', 15, 0)->default(0);
            $table->date('target_date')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('personal_transactions');
        Schema::dropIfExists('personal_accounts');
    }
};
