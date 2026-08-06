// database/migrations/2026_08_06_160001_add_usdt_to_payment_settings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'usdt_wallet_address', 'value' => ''],
            ['key' => 'usdt_qr_url', 'value' => ''],
            ['key' => 'usdt_network', 'value' => 'TRC20'],
            ['key' => 'usdt_wallet_label', 'value' => 'عنوان المحفظة'],
            ['key' => 'shamcash_wallet_label', 'value' => 'رقم المحفظة/الهاتف'],
        ];

        foreach ($settings as $setting) {
            DB::table('payment_settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }

    public function down(): void
    {
        DB::table('payment_settings')
            ->whereIn('key', [
                'usdt_wallet_address',
                'usdt_qr_url',
                'usdt_network',
                'usdt_wallet_label',
                'shamcash_wallet_label'
            ])
            ->delete();
    }
};
