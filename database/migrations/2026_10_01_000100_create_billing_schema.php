<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('api_key', 64)->unique();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('base_price', 14, 4);
            $table->string('billing_cycle', 16);
            $table->unsignedBigInteger('included_units');
            $table->decimal('overage_rate', 14, 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'name']);
            $table->index(['merchant_id', 'is_active']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();

            $table->unique(['merchant_id', 'email']);
            $table->index('merchant_id');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_cycle', 16);
            $table->string('status', 16);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::create('subscription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('plan_name');
            $table->decimal('base_price', 14, 4);
            $table->unsignedBigInteger('included_units');
            $table->decimal('overage_rate', 14, 6);
            $table->string('billing_cycle', 16);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'started_at', 'ended_at'], 'segments_customer_window_idx');
            $table->index(['merchant_id', 'started_at']);
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('units');
            $table->date('usage_date');
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'usage_date'], 'usage_events_customer_date_idx');
            $table->index(['merchant_id', 'usage_date'], 'usage_events_merchant_date_idx');
            $table->index('usage_date');
        });

        Schema::create('usage_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('total_units');
            $table->timestamps();

            $table->unique(['customer_id', 'usage_date'], 'usage_daily_customer_date_uq');
            $table->index(['merchant_id', 'usage_date'], 'usage_daily_merchant_date_idx');
            $table->index(['merchant_id', 'customer_id', 'usage_date'], 'usage_daily_merchant_customer_date_idx');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->decimal('base_amount', 14, 4);
            $table->decimal('overage_amount', 14, 4);
            $table->decimal('total_amount', 14, 4);
            $table->unsignedBigInteger('total_units')->default(0);
            $table->string('status', 16);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'cycle_start', 'cycle_end'], 'invoices_customer_cycle_uq');
            $table->index(['merchant_id', 'cycle_start']);
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('segment_start')->nullable();
            $table->date('segment_end')->nullable();
            $table->unsignedInteger('segment_days')->default(0);
            $table->unsignedBigInteger('units')->default(0);
            $table->unsignedBigInteger('included_units')->default(0);
            $table->unsignedBigInteger('overage_units')->default(0);
            $table->decimal('amount', 14, 4);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('usage_daily_aggregates');
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('subscription_segments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('merchants');
    }
};
