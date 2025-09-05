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
        Schema::create('chatbot_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('instance_id')->nullable();
            $table->json('trigger_keywords');
            $table->text('response_message');
            $table->enum('response_type', ['text', 'image', 'video', 'audio', 'document', 'menu', 'contact', 'location'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->integer('priority')->default(1);
            $table->enum('match_type', ['exact', 'contains', 'starts_with', 'ends_with', 'regex'])->default('contains');
            $table->boolean('case_sensitive')->default(false);
            $table->integer('response_delay')->default(0); // em segundos
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('instance_id')->references('instance_id')->on('whatsapp_instances')->onDelete('cascade');
            
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'response_type']);
            $table->index(['company_id', 'match_type']);
            $table->index(['company_id', 'priority']);
            $table->index(['instance_id', 'is_active']);
            $table->index('usage_count');
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_responses');
    }
};