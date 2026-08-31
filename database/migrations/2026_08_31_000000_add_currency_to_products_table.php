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
        Schema::table('products', function (Blueprint $table) {
            // العملة التي أُدخل بها سعر المنتج من قبل الأدمن (USD أو SYP)
            // الافتراضي SYP حتى لا تتأثر المنتجات القديمة الموجودة أصلاً بالليرة
            $table->enum('currency', ['USD', 'SYP'])
                ->default('SYP')
                ->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
