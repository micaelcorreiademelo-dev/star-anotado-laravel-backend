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
        Schema::create('greeting_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('instance_id')->nullable();
            $table->string('name');
            $table->text('message_content');
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'document', 'menu', 'contact', 'location'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->enum('trigger_type', ['first_contact', 'business_hours', 'after_hours', 'weekend', 'holiday', 'manual'])->default('first_contact');
            $table->integer('delay_seconds')->default(0);
            $table->time('business_start_time')->nullable();
            $table->time('business_end_time')->nullable();
            $table->json('business_days')->nullable(); // [1,2,3,4,5] para seg-sex
            $table->json('excluded_dates')->nullable(); // datas específicas para não enviar
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('instance_id')->references('instance_id')->on('whatsapp_instances')->onDelete('cascade');
            
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'trigger_type']);
            $table->index(['company_id', 'message_type']);
            $table->index(['instance_id', 'is_active']);
            $table->index(['instance_id', 'trigger_type']);
            $table->index('usage_count');
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greeting_messages');
    }
};