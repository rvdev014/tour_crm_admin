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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->comment('Название файла');
            $table->string('file_path')->comment('Путь к файлу');
            $table->string('file_type')->comment('Расширение файла');
            $table->integer('file_size')->comment('Размер файла');
            $table->string('type')->comment('Тип вложения (Документ, Медиа, Другое)');
            $table->unsignedBigInteger('attachable_id')->comment('ID объекта');
            $table->string('attachable_type')->comment('Тип объекта');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
