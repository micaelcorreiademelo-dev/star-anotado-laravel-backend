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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed']); // porcentagem ou valor fixo
            $table->decimal('value', 8, 2); // valor do desconto
            $table->decimal('minimum_order_value', 8, 2)->nullable(); // valor mínimo do pedido
            $table->decimal('maximum_discount', 8, 2)->nullable(); // desconto máximo (para porcentagem)
            $table->integer('usage_limit')->nullable(); // limite de uso total
            $table->integer('usage_limit_per_user')->nullable(); // limite de uso por usuário
            $table->integer('total_used')->default(0); // total de vezes usado
            $table->datetime('valid_from');
            $table->datetime('valid_until');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false); // se aplica a todas as empresas
            $table->json('applicable_categories')->nullable(); // categorias aplicáveis
            $table->json('applicable_items')->nullable(); // itens aplicáveis
            $table->json('applicable_users')->nullable(); // usuários aplicáveis
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['code', 'is_active']);
            $table->index(['company_id', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
            $table->index('is_global');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};