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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('instance_id');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('phone_number', 20);
            $table->text('message_content');
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'sticker'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->string('external_id')->nullable();
            $table->boolean('greeting_sent')->default(false);
            $table->boolean('chatbot_responded')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('instance_id')->references('instance_id')->on('whatsapp_instances')->onDelete('cascade');
            
            $table->index(['company_id', 'phone_number']);
            $table->index(['company_id', 'direction']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'message_type']);
            $table->index(['instance_id', 'phone_number']);
            $table->index(['phone_number', 'created_at']);
            $table->index('external_id');
            $table->index('received_at');
            $table->index(['greeting_sent', 'chatbot_responded']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};