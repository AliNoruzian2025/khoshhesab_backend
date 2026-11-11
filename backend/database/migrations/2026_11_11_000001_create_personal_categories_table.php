<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['user_id', 'type', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('personal_categories_initialized_at')->nullable()->after('is_completed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('personal_categories_initialized_at');
        });
        Schema::dropIfExists('personal_categories');
    }
};
