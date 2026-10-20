<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $aiCategoryId = DB::table('categories')->where('slug', 'ai')->value('id');

        if ($aiCategoryId === null) {
            return;
        }

        DB::table('videos')
            ->where('is_ai_generated', true)
            ->update(['category_id' => $aiCategoryId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing AI videos cannot safely be restored to their previous categories.
    }
};
