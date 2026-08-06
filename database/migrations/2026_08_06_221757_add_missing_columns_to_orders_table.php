// database/migrations/xxxx_xx_xx_add_missing_columns_to_orders_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('cash')->after('currency');
            }
            if (!Schema::hasColumn('orders', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('orders', 'payment_proof')) {
                $table->text('payment_proof')->nullable()->after('sender_name');
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_proof');
            }
            if (!Schema::hasColumn('orders', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = ['payment_method','transaction_id','sender_name','payment_proof','paid_at','ip_address','user_agent'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('orders', $c));
            if ($existing) $table->dropColumn($existing);
        });
    }
};
