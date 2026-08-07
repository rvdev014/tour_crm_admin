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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->unique()->comment('Digits-only phone id as returned by WhatsApp');
            $table->string('phone')->nullable();
            $table->string('profile_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable()->comment('Drives the 24h free-form reply window');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
