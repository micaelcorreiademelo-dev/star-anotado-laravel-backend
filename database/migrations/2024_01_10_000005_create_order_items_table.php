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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->string('item_name'); // snapshot do nome no momento do pedido
            $table->text('item_description')->nullable(); // snapshot da descrição
            $table->decimal('item_price', 8, 2); // snapshot do preço
            $table->integer('quantity');
            $table->decimal('total_price', 8, 2);
            $table->text('notes')->nullable(); // observações específicas do item
            $table->json('customizations')->nullable(); // personalizações do item
            $table->timestamps();
            
            $table->index(['order_id', 'item_id']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};