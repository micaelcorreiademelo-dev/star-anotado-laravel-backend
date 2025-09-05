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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('coupon_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'pix'])->nullable();
            $table->decimal('subtotal', 8, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->decimal('total', 8, 2);
            $table->text('notes')->nullable();
            $table->string('delivery_address');
            $table->string('delivery_address_number');
            $table->string('delivery_address_complement')->nullable();
            $table->string('delivery_neighborhood');
            $table->string('delivery_city');
            $table->string('delivery_state', 2);
            $table->string('delivery_zipcode', 9);
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->decimal('delivery_distance', 8, 2)->nullable(); // em km
            $table->integer('estimated_delivery_time')->nullable(); // em minutos
            $table->datetime('estimated_delivery_at')->nullable();
            $table->datetime('confirmed_at')->nullable();
            $table->datetime('preparing_at')->nullable();
            $table->datetime('ready_at')->nullable();
            $table->datetime('out_for_delivery_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->integer('rating')->nullable();
            $table->text('review')->nullable();
            $table->datetime('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('estimated_delivery_at');
            $table->index('delivered_at');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};