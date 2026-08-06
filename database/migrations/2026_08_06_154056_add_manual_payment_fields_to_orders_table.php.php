// database/migrations/2026_08_06_160000_add_missing_manual_payment_fields_to_orders_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Only add columns that don't exist
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

            // Update status enum to include pending_approval if not already there
            // Note: SQLite doesn't support enum modification easily, so we'll handle this in the model
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'sender_name', 'payment_proof', 'paid_at']);
        });
    }
};
