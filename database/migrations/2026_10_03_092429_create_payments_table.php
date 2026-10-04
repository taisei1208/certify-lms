<?php

declare(strict_types=1);

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
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('meeting_pack_id')->constrained('meeting_packs')->restrictOnDelete();
            $table->string('meeting_pack_name', 100);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('jpy');

            $table->string('status', 20)->default('pending');

            $table->string('stripe_checkout_session_id', 255)->nullable()->unique();

            $table->string('stripe_payment_intent_id', 255)->nullable()->unique();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['meeting_pack_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
