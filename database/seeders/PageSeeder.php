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
     * The About page tells the story from the home page, in the store's own words.
     * The other pages hold PLACEHOLDER text until the real wording is written in
     * the admin panel.
     */
    private const BODIES = [
        'about' => [
            '<p>اسمنا مستوحى من خشب الجوز: صلابته ودفؤه وأصالته التي تدوم طويلاً في المنزل. ومن هذا المعنى نختار كل قطعة في المعرض.</p>'
            .'<p>نؤمن أن الأثاث ليس مجرد قطع خشبية، بل تحف فنية تجمع بين الإبداع والوضوح. لذلك نقدّم تشكيلة عصرية هادئة تناسب البيت العراقي، ونوصلها إلى بابك في أي محافظة، والدفع نقداً عند الاستلام.</p>'
            .'<p>جوز كاليري – حيث يلتقي الذوق بالفخامة.</p>',
            '<p>Our name comes from walnut wood (joz): solid, warm and built to last in a home. We choose every piece in the gallery with that in mind.</p>'
            .'<p>Furniture should be more than wood. It should be a piece of art that brings creativity and clarity together. We offer a calm, modern collection suited to Iraqi homes, delivered to your door in any governorate, with cash on delivery.</p>'
            .'<p>Joz Gallery, where taste meets luxury.</p>',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PAGES as [$slug, $titleAr, $titleEn]) {
            [$bodyAr, $bodyEn] = self::BODIES[$slug] ?? [
                '<p>نص مؤقت، سيُستبدل بالمحتوى الفعلي لهذه الصفحة.</p>',
                '<p>Placeholder text, to be replaced with the real content of this page.</p>',
            ];

            Page::query()->firstOrCreate(['slug' => $slug], [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
            ]);
        }
    }
}
