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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle', 20);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'billing_cycle', 'currency']);
            $table->index(['plan_id', 'is_active']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('trial_ends_at');
            $table->index('grace_period_ends_at');
            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'grace_period_ends_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->string('idempotency_key')->unique();
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('attempted_at');
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index('attempted_at');
        });

        Schema::create('subscription_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['subscription_id', 'changed_at']);
        });

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_name');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['processed_at', 'occurred_at']);
            $table->index('event_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('subscription_status_histories');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
