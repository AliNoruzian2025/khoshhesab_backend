<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['dang', 'account']);
            $table->string('avatar')->nullable();
            $table->foreignId('creator_id')->constrained('users');
            $table->string('invite_code', 10)->unique();
            $table->boolean('require_approval')->default(true);
            $table->datetime('due_date')->nullable();
            $table->datetime('reminder_date')->nullable();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('parent_group_id')->nullable()->constrained('groups');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
