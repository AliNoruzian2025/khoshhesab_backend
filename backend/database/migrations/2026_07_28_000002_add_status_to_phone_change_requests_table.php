<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_change_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('phone_change_requests', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
