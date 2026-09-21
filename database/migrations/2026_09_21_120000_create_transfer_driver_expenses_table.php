<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_driver_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            // Who ENTERED it: the driver, or a dispatcher on their behalf. Kept if the account is deleted.
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 32);
            // Sums are whole numbers in practice, but the column is money-shaped so a currency picker or
            // decimals can be added without a migration.
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('UZS');

            // PRIVATE disk (storage/app), never public/: a receipt is a financial document. Served only
            // through authenticated routes.
            $table->string('receipt_path');
            $table->string('receipt_mime', 64);
            $table->unsignedInteger('receipt_size');

            // Random token generated when the form is rendered. A retry or double tap on a bad connection
            // re-sends the same token and must not create a second expense.
            $table->string('submission_key', 36)->nullable()->unique();

            // new | approved | rejected — an operator's review. Does NOT feed any financial total.
            $table->string('status', 16)->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_driver_expenses');
    }
};
