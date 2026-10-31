<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    private const PAGES = [
        ['about', 'من نحن', 'About us'],
        ['contact', 'تواصل معنا', 'Contact us'],
        ['delivery', 'التوصيل والأجور', 'Delivery & fees'],
        ['returns', 'الاستبدال والإرجاع', 'Returns & exchanges'],
        ['privacy', 'سياسة الخصوصية', 'Privacy policy'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PAGES as [$slug, $titleAr, $titleEn]) {
            // PLACEHOLDER text: the real wording is written in the admin panel.
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => '<p>نص مؤقت، سيُستبدل بالمحتوى الفعلي لهذه الصفحة.</p>',
                'body_en' => '<p>Placeholder text, to be replaced with the real content of this page.</p>',
            ]);
        }
    }
}
