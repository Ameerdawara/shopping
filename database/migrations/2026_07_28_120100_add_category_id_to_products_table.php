<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nullable on purpose: lets us backfill before enforcing anything,
            // and keeps old rows valid if a category is later deleted.
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('categories')
                ->nullOnDelete();
        });

        // Backfill: turn every distinct legacy `category` string into a real
        // Category row, then point existing products at it. The old `category`
        // string column is intentionally left in place (not dropped) so any
        // code still reading it directly keeps working.
        $distinctCategories = DB::table('products')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        foreach ($distinctCategories as $categoryName) {
            $categoryId = DB::table('categories')->insertGetId([
                'name'       => $categoryName,
                'slug'       => Str::slug($categoryName),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')
                ->where('category', $categoryName)
                ->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
