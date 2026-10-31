<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\OfferScope;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Spatie\MediaLibrary\HasMedia;

/**
 * The demo storefront's catalog. Names, prices, photos and sections come from the
 * demo. The demo has no stock, materials, dimensions or descriptions, so those are
 * PLACEHOLDER values to be replaced with the store's real data.
 */
class DemoCatalogSeeder extends Seeder
{
    private const OFFER_DAYS = 14;

    private const COUPON_DAYS = 60;

    private const CATEGORIES = [
        ['living-room', 'غرف المعيشة', 'Living Room', 'sec-living.webp'],
        ['bedroom', 'غرف النوم', 'Bedroom', 'sec-bedroom.webp'],
        ['dining', 'السفرة', 'Dining', 'sec-dining.webp'],
        ['decor', 'الديكور', 'Décor', 'sec-decor.webp'],
        ['mirrors', 'المرايا', 'Mirrors', 'sec-mirrors.webp'],
        ['lighting', 'الإضاءة', 'Lighting', 'sec-lighting.webp'],
    ];

    /**
     * price is the full price before any offer. dimensions are width, depth, height in cm.
     */
    private const PRODUCTS = [
        ['slug' => 'emerald-velvet-sofa', 'category' => 'living-room', 'name_ar' => 'كنبة مخمل زمردية', 'name_en' => 'Emerald Velvet Sofa', 'price' => 1_450_000, 'stock' => 10, 'image' => 'p-emerald-sofa.webp', 'material' => ['مخمل', 'Velvet'], 'color' => ['زمردي', 'Emerald'], 'dimensions' => [220, 90, 80], 'is_featured' => true],
        ['slug' => 'sand-three-seater', 'category' => 'living-room', 'name_ar' => 'كنبة رملية ثلاثية', 'name_en' => 'Sand Three-Seater Sofa', 'price' => 1_850_000, 'stock' => 10, 'image' => 'p-sand-sofa.webp', 'material' => ['قماش', 'Fabric'], 'color' => ['رملي', 'Sand'], 'dimensions' => [240, 95, 82], 'is_new' => true],
        ['slug' => 'cream-boucle-armchair', 'category' => 'living-room', 'name_ar' => 'كرسي بوكليه كريمي', 'name_en' => 'Cream Bouclé Armchair', 'price' => 650_000, 'stock' => 10, 'image' => 'p-boucle-chair.webp', 'material' => ['بوكليه', 'Bouclé'], 'color' => ['كريمي', 'Cream'], 'dimensions' => [80, 78, 76], 'is_featured' => true],
        ['slug' => 'swivel-lounge-chair', 'category' => 'living-room', 'name_ar' => 'كرسي استرخاء دوّار', 'name_en' => 'Swivel Lounge Chair', 'price' => 720_000, 'stock' => 10, 'image' => 'p-lounge-chair.webp', 'material' => ['قماش', 'Fabric'], 'color' => ['بيج', 'Beige'], 'dimensions' => [75, 75, 85], 'is_new' => true],
        ['slug' => 'walnut-side-table', 'category' => 'living-room', 'name_ar' => 'طاولة جانبية من خشب الجوز', 'name_en' => 'Walnut Side Table', 'price' => 240_000, 'stock' => 10, 'image' => 'p-walnut-side.webp', 'material' => ['خشب الجوز', 'Walnut wood'], 'color' => ['جوزي', 'Walnut'], 'dimensions' => [50, 50, 55], 'is_featured' => true],
        ['slug' => 'brass-pendant-trio', 'category' => 'lighting', 'name_ar' => 'ثريا نحاسية ثلاثية', 'name_en' => 'Brass Pendant Trio', 'price' => 395_000, 'stock' => 10, 'image' => 'p-brass-pendant.webp', 'material' => ['نحاس', 'Brass'], 'color' => ['نحاسي', 'Brass'], 'dimensions' => [60, 20, 120], 'is_featured' => true],
        ['slug' => 'charcoal-floor-lamp', 'category' => 'lighting', 'name_ar' => 'مصباح أرضي فحمي', 'name_en' => 'Charcoal Floor Lamp', 'price' => 145_000, 'stock' => 10, 'image' => 'p-floor-lamp.webp', 'material' => ['معدن', 'Metal'], 'color' => ['فحمي', 'Charcoal'], 'dimensions' => [30, 30, 160], 'is_new' => true],
        ['slug' => 'ivory-ceramic-vase', 'category' => 'decor', 'name_ar' => 'مزهرية سيراميك عاجية', 'name_en' => 'Ivory Ceramic Vase', 'price' => 85_000, 'stock' => 10, 'image' => 'p-ceramic-vase.webp', 'material' => ['سيراميك', 'Ceramic'], 'color' => ['عاجي', 'Ivory'], 'dimensions' => [18, 18, 35], 'is_new' => true],
        ['slug' => 'royal-tufted-bed', 'category' => 'bedroom', 'name_ar' => 'سرير كابيتونيه ملكي', 'name_en' => 'Royal Tufted Bed', 'price' => 2_350_000, 'stock' => 10, 'image' => 'p-tufted-bed.webp', 'material' => ['قماش مبطّن', 'Upholstered fabric'], 'color' => ['بيج', 'Beige'], 'dimensions' => [190, 210, 120]],
        ['slug' => 'natural-oak-bed', 'category' => 'bedroom', 'name_ar' => 'سرير خشب بلوط طبيعي', 'name_en' => 'Natural Oak Bed', 'price' => 1_650_000, 'stock' => 10, 'image' => 'p-oak-bed.webp', 'material' => ['خشب البلوط', 'Oak wood'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [170, 205, 95]],
        // Low stock on purpose, so the red badge can be seen in the admin table.
        ['slug' => 'emerald-dining-set', 'category' => 'dining', 'name_ar' => 'طقم سفرة زمردي 6 كراسي', 'name_en' => 'Emerald Dining Set, 6 Chairs', 'price' => 2_900_000, 'stock' => 2, 'image' => 'p-dining-set.webp', 'material' => ['خشب ومخمل', 'Wood and velvet'], 'color' => ['زمردي', 'Emerald'], 'dimensions' => [180, 90, 75]],
        ['slug' => 'round-brass-mirror', 'category' => 'mirrors', 'name_ar' => 'مرآة دائرية بإطار نحاسي', 'name_en' => 'Round Brass-Frame Mirror', 'price' => 310_000, 'stock' => 4, 'image' => 'p-oval-mirror.webp', 'material' => ['زجاج ونحاس', 'Glass and brass'], 'color' => ['نحاسي', 'Brass'], 'dimensions' => [80, 3, 80]],
        ['slug' => 'gold-silk-rug', 'category' => 'decor', 'name_ar' => 'سجادة حرير بنقشة ذهبية', 'name_en' => 'Gold Pattern Silk Rug', 'price' => 520_000, 'stock' => 10, 'image' => 'p-silk-rug.webp', 'material' => ['حرير', 'Silk'], 'color' => ['ذهبي', 'Gold'], 'dimensions' => [200, 300, 1]],
    ];

    /**
     * Reproduces the demo's sale prices, for example 1,450,000 down to 1,232,500.
     */
    private const OFFERS = [
        ['walnut-side-table', DiscountType::Percent, 20],
        ['gold-silk-rug', DiscountType::Percent, 20],
        ['emerald-velvet-sofa', DiscountType::Percent, 15],
        ['cream-boucle-armchair', DiscountType::Percent, 15],
        ['royal-tufted-bed', DiscountType::Fixed, 250_000],
    ];

    private const BANNERS = [
        [
            'eyebrow_ar' => 'جوز كاليري للمنزل', 'eyebrow_en' => 'Joz Gallery for Home',
            'title_ar' => 'حيث يلتقي الذوق بالفخامة', 'title_en' => 'Where taste meets luxury',
            'subtitle_ar' => 'أثاث وديكور مختار بعناية لكل زاوية في بيتك.', 'subtitle_en' => 'Furniture and décor chosen for every corner of your home.',
            'button_ar' => 'تسوّق الآن', 'button_en' => 'Shop now',
            'link' => '/#sections', 'image' => 'hero-1.webp', 'image_mobile' => 'hero-1-m.webp',
        ],
        [
            'eyebrow_ar' => 'مجموعة البوكليه', 'eyebrow_en' => 'The Bouclé Collection',
            'title_ar' => 'نعومة الكريم في غرفة معيشتك', 'title_en' => 'Soft cream for your living room',
            'subtitle_ar' => 'كنب وكراسي بخطوط منحنية وأقمشة دافئة.', 'subtitle_en' => 'Curved sofas and chairs in warm, textured fabric.',
            'button_ar' => 'اكتشف المجموعة', 'button_en' => 'Explore the collection',
            'link' => '/category/living-room', 'image' => 'hero-2.webp', 'image_mobile' => 'hero-2-m.webp',
        ],
        [
            'eyebrow_ar' => 'عروض الموسم', 'eyebrow_en' => 'Seasonal offers',
            'title_ar' => 'خصم حتى 20% على قطع مختارة', 'title_en' => 'Up to 20% off selected pieces',
            'subtitle_ar' => 'العرض ساري لفترة محدودة.', 'subtitle_en' => 'For a limited time only.',
            'button_ar' => 'شاهد العروض', 'button_en' => 'See the offers',
            'link' => '/offers', 'image' => 'hero-3.webp', 'image_mobile' => 'hero-3-m.webp',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories);

        $this->seedOffers($products);
        $this->seedBanners();

        Coupon::query()->firstOrCreate(['code' => 'JOZ10'], [
            'type' => DiscountType::Percent,
            'value' => 10,
            'min_total' => 100_000,
            'usage_limit' => 100,
            'expires_at' => now()->addDays(self::COUPON_DAYS)->endOfDay(),
        ]);
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $index => [$slug, $nameAr, $nameEn, $image]) {
            $category = Category::query()->firstOrCreate(['slug' => $slug], [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'sort_order' => $index,
            ]);

            $this->attachImage($category, $image, 'image');

            $categories[$slug] = $category;
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, Product>
     */
    private function seedProducts(array $categories): array
    {
        $products = [];

        foreach (self::PRODUCTS as $index => $data) {
            $product = Product::query()->firstOrCreate(['slug' => $data['slug']], [
                // Stepped back in time so "newest first" keeps the demo's order.
                'created_at' => now()->subMinutes($index),
                'category_id' => $categories[$data['category']]->id,
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'description_ar' => $data['name_ar'].'. وصف مؤقت، سيُستبدل بالتفاصيل الفعلية للقطعة.',
                'description_en' => $data['name_en'].'. Placeholder description, to be replaced with the real product details.',
                'price' => $data['price'],
                'stock' => $data['stock'],
                'material_ar' => $data['material'][0],
                'material_en' => $data['material'][1],
                'color_ar' => $data['color'][0],
                'color_en' => $data['color'][1],
                'width_cm' => $data['dimensions'][0],
                'depth_cm' => $data['dimensions'][1],
                'height_cm' => $data['dimensions'][2],
                'is_new' => $data['is_new'] ?? false,
                'is_featured' => $data['is_featured'] ?? false,
            ]);

            $this->attachImage($product, $data['image'], 'gallery');

            $products[$data['slug']] = $product;
        }

        return $products;
    }

    /**
     * The demo's own countdown has already ended, so offers run from today.
     *
     * @param  array<string, Product>  $products
     */
    private function seedOffers(array $products): void
    {
        foreach (self::OFFERS as [$slug, $type, $value]) {
            Offer::query()->firstOrCreate(
                ['scope' => OfferScope::Product, 'target_id' => $products[$slug]->id],
                [
                    'title_ar' => 'عروض الموسم',
                    'title_en' => 'Seasonal offers',
                    'type' => $type,
                    'value' => $value,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(self::OFFER_DAYS)->endOfDay(),
                ],
            );
        }
    }

    private function seedBanners(): void
    {
        foreach (self::BANNERS as $index => $data) {
            $banner = Banner::query()->firstOrCreate(
                ['title_en' => $data['title_en']],
                ['sort_order' => $index, ...collect($data)->except(['image', 'image_mobile'])->all()],
            );

            $this->attachImage($banner, $data['image'], 'image');
            $this->attachImage($banner, $data['image_mobile'], 'image_mobile');
        }
    }

    /**
     * @param  HasMedia&Model  $model
     */
    private function attachImage(HasMedia $model, string $file, string $collection): void
    {
        if ($model->getMedia($collection)->isNotEmpty()) {
            return;
        }

        $model->addMedia(database_path("seeders/demo/{$file}"))
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }
}
