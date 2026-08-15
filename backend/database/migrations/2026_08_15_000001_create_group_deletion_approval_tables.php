<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_deletion_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deletion_request_id')->constrained('group_deletion_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->timestamps();
            $table->unique(['deletion_request_id', 'user_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_seen');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
        Schema::dropIfExists('group_deletion_votes');
        Schema::dropIfExists('group_deletion_requests');
    }
};
