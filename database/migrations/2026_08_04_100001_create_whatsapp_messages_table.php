<?php

use App\Enums\WhatsAppDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
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
            $table->foreignId('whatsapp_contact_id')->constrained()->cascadeOnDelete();
            $table->string('wa_message_id')->unique()->comment('Meta wamid, used to dedupe retried webhooks');
            $table->string('direction')->default(WhatsAppDirection::In->value);
            $table->string('type')->default(WhatsAppMessageType::Text->value);
            $table->text('body')->nullable();
            $table->jsonb('payload')->nullable()->comment('Raw Meta webhook/response object');
            $table->string('status')->default(WhatsAppMessageStatus::Pending->value);
            $table->string('error_message')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('wa_timestamp')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_contact_id', 'wa_timestamp']);
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
