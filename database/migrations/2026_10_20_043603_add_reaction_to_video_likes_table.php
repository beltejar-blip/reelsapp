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
        if (! Schema::hasTable('video_likes') || Schema::hasColumn('video_likes', 'reaction')) {
            return;
        }

        Schema::table('video_likes', function (Blueprint $table) {
            $table->string('reaction')->default('like');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('video_likes') || ! Schema::hasColumn('video_likes', 'reaction')) {
            return;
        }

        Schema::table('video_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
