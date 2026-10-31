<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    // Phone, WhatsApp and Instagram come from the demo storefront.
    private const DEFAULTS = [
        'store_phone' => '07740243538',
        'whatsapp_number' => '9647740243538',
        'instagram' => 'joz.gallery',
        'address' => '',
        'low_stock_threshold' => '5',
        'telegram_chat_id' => '',
        'notification_email' => '',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
