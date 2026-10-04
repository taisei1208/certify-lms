<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談回数取引と追加面談パック購入記録を関連付ける。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->dropIndex(['related_payment_id']);
            $table->unique('related_payment_id');
            $table->foreign('related_payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->dropForeign(['related_payment_id']);
            $table->dropUnique(['related_payment_id']);
            $table->index('related_payment_id');
        });
    }
};
