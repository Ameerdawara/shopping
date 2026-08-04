<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('total_price');
            $table->string('payment_method')->nullable()->after('currency');
            $table->string('invoice_number')->nullable()->unique()->after('payment_method');
            $table->string('shamcash_transaction_ref')->nullable()->after('invoice_number');
            $table->timestamp('paid_at')->nullable()->after('shamcash_transaction_ref');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'payment_method',
                'invoice_number',
                'shamcash_transaction_ref',
                'paid_at',
            ]);
        });
    }
};
