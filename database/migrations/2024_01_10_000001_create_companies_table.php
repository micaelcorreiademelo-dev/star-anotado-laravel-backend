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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address');
            $table->string('address_number');
            $table->string('address_complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state', 2);
            $table->string('zipcode', 9);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->time('opening_time');
            $table->time('closing_time');
            $table->json('opening_days'); // [1,2,3,4,5,6,7] onde 1=segunda
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('minimum_order_value', 8, 2)->default(0);
            $table->integer('delivery_time')->default(30); // em minutos
            $table->decimal('delivery_radius', 8, 2)->default(10); // em km
            $table->boolean('accepts_cash')->default(true);
            $table->boolean('accepts_card')->default(true);
            $table->boolean('accepts_pix')->default(true);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->integer('total_orders')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'is_featured']);
            $table->index(['city', 'state']);
            $table->index('rating');
            $table->index('display_order');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};