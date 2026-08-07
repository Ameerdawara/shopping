<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمود exchange_rate إلى جدول orders.
     * يُستخدم لتخزين سعر الصرف وقت إنشاء الطلب عندما تكون العملة USD،
     * حتى يمكن لاحقاً عرض المبلغ بالعملة الصحيحة (بدل عرضه دائماً بالليرة السورية).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
