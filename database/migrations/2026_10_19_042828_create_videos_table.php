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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('category_id')->constrained()->cascadeOnDelete();
$table->string('caption')->nullable();
$table->string('path');
$table->string('thumbnail_path')->nullable();
$table->boolean('is_ai_generated')->default(false);
$table->string('ai_label_source')->default('creator');
            $table->timestamps();
            $table->index(['category_id', 'is_ai_generated', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
