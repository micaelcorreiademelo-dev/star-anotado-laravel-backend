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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('promotional_price', 8, 2)->nullable();
            $table->boolean('has_promotion')->default(false);
            $table->datetime('promotion_start_date')->nullable();
            $table->datetime('promotion_end_date')->nullable();
            $table->integer('preparation_time')->default(15); // em minutos
            $table->integer('calories')->nullable();
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_gluten_free')->default(false);
            $table->boolean('is_lactose_free')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->enum('spicy_level', ['mild', 'medium', 'hot', 'very_hot'])->nullable();
            $table->json('allergens')->nullable(); // ['gluten', 'lactose', 'nuts', etc]
            $table->json('nutritional_info')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->integer('total_sales')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'category_id', 'is_active']);
            $table->index(['company_id', 'is_available', 'is_active']);
            $table->index(['company_id', 'is_featured']);
            $table->index(['company_id', 'display_order']);
            $table->index('rating');
            $table->index('total_sales');
            $table->index(['has_promotion', 'promotion_start_date', 'promotion_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};