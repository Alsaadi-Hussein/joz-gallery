<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    // PLACEHOLDER fees: replace with the real delivery prices in the admin panel.
    private const BAGHDAD_FEE = 5_000;

    private const OTHER_FEE = 8_000;

    private const GOVERNORATES = [
        ['بغداد', 'Baghdad'],
        ['البصرة', 'Basra'],
        ['نينوى', 'Nineveh'],
        ['أربيل', 'Erbil'],
        ['السليمانية', 'Sulaymaniyah'],
        ['دهوك', 'Duhok'],
        ['كركوك', 'Kirkuk'],
        ['ديالى', 'Diyala'],
        ['الأنبار', 'Anbar'],
        ['بابل', 'Babil'],
        ['كربلاء', 'Karbala'],
        ['النجف', 'Najaf'],
        ['واسط', 'Wasit'],
        ['صلاح الدين', 'Saladin'],
        ['ذي قار', 'Dhi Qar'],
        ['ميسان', 'Maysan'],
        ['المثنى', 'Muthanna'],
        ['القادسية', 'Al-Qadisiyah'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::GOVERNORATES as $index => [$nameAr, $nameEn]) {
            Governorate::query()->firstOrCreate(['name_en' => $nameEn], [
                'name_ar' => $nameAr,
                'delivery_fee' => $nameEn === 'Baghdad' ? self::BAGHDAD_FEE : self::OTHER_FEE,
                'sort_order' => $index,
            ]);
        }
    }
}
