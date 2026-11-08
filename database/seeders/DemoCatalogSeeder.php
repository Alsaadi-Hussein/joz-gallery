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
 * design preview. The preview has no stock, materials, dimensions or descriptions,
 * so those are PLACEHOLDER values to be replaced with the store's real data.
 */
class DemoCatalogSeeder extends Seeder
{
    private const OFFER_DAYS = 14;

    private const COUPON_DAYS = 60;

    /**
     * The sections of the design preview, in its order. "Joz Picks" is not a
     * section: it lists the featured products.
     */
    private const CATEGORIES = [
        ['mirrors', 'مرايات', 'Mirrors', 'sec-mirrors.webp'],
        ['tables', 'طاولات', 'Tables', 'sec-tables.webp'],
        ['consoles', 'كونسول', 'Consoles', 'sec-consoles.webp'],
        ['plants', 'زرعات', 'Plants', 'sec-plants.webp'],
        ['kitchen', 'اكسسوارات مطبخ', 'Kitchen Accessories', 'sec-kitchen.webp'],
        ['lamps', 'تيبل لامب وشمعدان', 'Table Lamps & Candlesticks', 'sec-lamps.webp'],
        ['vases', 'فازات', 'Vases', 'sec-vases.webp'],
        ['accessories', 'اكسسوارات', 'Accessories', 'sec-decor.webp'],
        ['paintings', 'لوحات', 'Paintings', 'sec-paintings.webp'],
        ['chairs', 'كراسي', 'Chairs', 'sec-chairs.webp'],
        ['furniture', 'اثاث', 'Furniture', 'sec-living.webp'],
    ];

    /**
     * price is the full price before any offer. dimensions are width, depth, height in cm.
     * extra lists close-ups of the same photo, so the demo shows the multi-photo gallery.
     */
    private const PRODUCTS = [
        ['slug' => 'round-brass-mirror', 'category' => 'mirrors', 'name_ar' => 'مرآة دائرية بإطار نحاسي', 'name_en' => 'Round Brass-Frame Mirror', 'price' => 310_000, 'stock' => 4, 'image' => 'p-oval-mirror.webp', 'material' => ['زجاج ونحاس', 'Glass and brass'], 'color' => ['نحاسي', 'Brass'], 'dimensions' => [80, 3, 80], 'extra' => ['p-oval-mirror-detail-1.webp', 'p-oval-mirror-detail-2.webp'], 'is_new' => true, 'is_featured' => true],
        ['slug' => 'oval-wall-mirror', 'category' => 'mirrors', 'name_ar' => 'مرآة حائط بيضاوية', 'name_en' => 'Oval Wall Mirror', 'price' => 285_000, 'stock' => 6, 'image' => 'p-wall-mirror.webp', 'material' => ['زجاج وخشب', 'Glass and wood'], 'color' => ['ذهبي', 'Gold'], 'dimensions' => [60, 3, 90], 'extra' => ['p-wall-mirror-detail-1.webp', 'p-wall-mirror-detail-2.webp']],
        ['slug' => 'walnut-side-table', 'category' => 'tables', 'name_ar' => 'طاولة جانبية من خشب الجوز', 'name_en' => 'Walnut Side Table', 'price' => 240_000, 'stock' => 10, 'image' => 'p-walnut-side.webp', 'material' => ['خشب الجوز', 'Walnut wood'], 'color' => ['جوزي', 'Walnut'], 'dimensions' => [50, 50, 55]],
        ['slug' => 'round-dining-table', 'category' => 'tables', 'name_ar' => 'طاولة طعام دائرية بيضاء', 'name_en' => 'Round White Dining Table', 'price' => 450_000, 'stock' => 5, 'image' => 'p-round-table.webp', 'material' => ['خشب مطلي', 'Lacquered wood'], 'color' => ['أبيض', 'White'], 'dimensions' => [110, 110, 75], 'is_new' => true],
        ['slug' => 'c-side-table', 'category' => 'tables', 'name_ar' => 'طاولة جانبية خشبية بتصميم C', 'name_en' => 'C-Shaped Wooden Side Table', 'price' => 135_000, 'stock' => 8, 'image' => 'p-c-table.webp', 'material' => ['خشب ومعدن', 'Wood and metal'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [40, 35, 60]],
        ['slug' => 'walnut-gold-console', 'category' => 'consoles', 'name_ar' => 'كونسول خشب جوز بلمسة ذهبية', 'name_en' => 'Walnut & Gold Console', 'price' => 890_000, 'stock' => 4, 'image' => 'p-sideboard.webp', 'material' => ['خشب الجوز ومعدن مذهّب', 'Walnut wood and gilded metal'], 'color' => ['جوزي وذهبي', 'Walnut and gold'], 'dimensions' => [160, 40, 80], 'extra' => ['p-sideboard-detail-1.webp', 'p-sideboard-detail-2.webp'], 'is_new' => true, 'is_featured' => true],
        ['slug' => 'rattan-console', 'category' => 'consoles', 'name_ar' => 'كونسول خيزران طبيعي', 'name_en' => 'Natural Rattan Console', 'price' => 640_000, 'stock' => 6, 'image' => 'p-rattan-console.webp', 'material' => ['خيزران طبيعي وخشب', 'Natural rattan and wood'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [150, 40, 75], 'extra' => ['p-rattan-console-detail-1.webp', 'p-rattan-console-detail-2.webp']],
        ['slug' => 'bonsai-ficus', 'category' => 'plants', 'name_ar' => 'بونساي فيكس بأصيص أبيض', 'name_en' => 'Bonsai Ficus, White Pot', 'price' => 95_000, 'stock' => 12, 'image' => 'p-bonsai.webp', 'material' => ['نبتة طبيعية بأصيص سيراميك', 'Live plant in a ceramic pot'], 'color' => ['أخضر وأبيض', 'Green and white'], 'dimensions' => [30, 30, 40], 'extra' => ['p-bonsai-detail-1.webp', 'p-bonsai-detail-2.webp'], 'is_new' => true, 'is_featured' => true],
        ['slug' => 'aloe-succulent', 'category' => 'plants', 'name_ar' => 'نبتة صبار بأصيص نعناعي', 'name_en' => 'Succulent, Mint Pot', 'price' => 25_000, 'stock' => 20, 'image' => 'p-succulent.webp', 'material' => ['نبتة طبيعية بأصيص سيراميك', 'Live plant in a ceramic pot'], 'color' => ['نعناعي', 'Mint'], 'dimensions' => [12, 12, 18]],
        ['slug' => 'ficus-plant', 'category' => 'plants', 'name_ar' => 'نبتة فيكس داخلية', 'name_en' => 'Indoor Ficus Plant', 'price' => 60_000, 'stock' => 10, 'image' => 'p-ficus.webp', 'material' => ['نبتة طبيعية بأصيص بلاستيك', 'Live plant in a plastic pot'], 'color' => ['أخضر', 'Green'], 'dimensions' => [35, 35, 90]],
        ['slug' => 'terracotta-cactus', 'category' => 'plants', 'name_ar' => 'صبار بأصيص فخاري', 'name_en' => 'Cactus, Terracotta Pot', 'price' => 20_000, 'stock' => 20, 'image' => 'p-cactus.webp', 'material' => ['نبتة طبيعية بأصيص فخاري', 'Live plant in a terracotta pot'], 'color' => ['فخاري', 'Terracotta'], 'dimensions' => [12, 12, 20]],
        ['slug' => 'cast-iron-casserole', 'category' => 'kitchen', 'name_ar' => 'قدر حديد زهر برتقالي', 'name_en' => 'Orange Cast-Iron Casserole', 'price' => 120_000, 'stock' => 8, 'image' => 'p-casserole.webp', 'material' => ['حديد زهر مطلي بالمينا', 'Enamelled cast iron'], 'color' => ['برتقالي', 'Orange'], 'dimensions' => [30, 24, 16], 'is_new' => true],
        ['slug' => 'ceramic-cup-set', 'category' => 'kitchen', 'name_ar' => 'طقم أكواب سيراميك', 'name_en' => 'Ceramic Cup Set', 'price' => 45_000, 'stock' => 15, 'image' => 'p-cups.webp', 'material' => ['سيراميك', 'Ceramic'], 'color' => ['بيج', 'Beige'], 'dimensions' => [30, 20, 10]],
        ['slug' => 'porcelain-mug', 'category' => 'kitchen', 'name_ar' => 'كوب قهوة بورسلين', 'name_en' => 'Porcelain Coffee Mug', 'price' => 15_000, 'stock' => 25, 'image' => 'p-mug.webp', 'material' => ['بورسلين', 'Porcelain'], 'color' => ['أبيض', 'White'], 'dimensions' => [12, 9, 10]],
        ['slug' => 'tripod-table-lamp', 'category' => 'lamps', 'name_ar' => 'تيبل لامب خشبي ثلاثي الأرجل', 'name_en' => 'Wooden Tripod Table Lamp', 'price' => 110_000, 'stock' => 8, 'image' => 'p-tripod-lamp.webp', 'material' => ['خشب وقماش كتان', 'Wood and linen'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [35, 35, 55], 'extra' => ['p-tripod-lamp-detail-1.webp', 'p-tripod-lamp-detail-2.webp'], 'is_new' => true],
        ['slug' => 'glass-candle-holder', 'category' => 'lamps', 'name_ar' => 'شمعدان زجاجي بشمعة معطرة', 'name_en' => 'Glass Candle Holder', 'price' => 30_000, 'stock' => 30, 'image' => 'p-candle.webp', 'material' => ['زجاج', 'Glass'], 'color' => ['شفاف', 'Clear'], 'dimensions' => [10, 10, 12]],
        ['slug' => 'brass-pendant-trio', 'category' => 'lamps', 'name_ar' => 'ثريا نحاسية ثلاثية', 'name_en' => 'Brass Pendant Trio', 'price' => 395_000, 'stock' => 10, 'image' => 'p-brass-pendant.webp', 'material' => ['نحاس', 'Brass'], 'color' => ['نحاسي', 'Brass'], 'dimensions' => [60, 20, 120]],
        ['slug' => 'ivory-ceramic-vase', 'category' => 'vases', 'name_ar' => 'فازة سيراميك عاجية', 'name_en' => 'Ivory Ceramic Vase', 'price' => 85_000, 'stock' => 10, 'image' => 'p-ceramic-vase.webp', 'material' => ['سيراميك', 'Ceramic'], 'color' => ['عاجي', 'Ivory'], 'dimensions' => [18, 18, 35], 'extra' => ['p-ceramic-vase-detail-1.webp', 'p-ceramic-vase-detail-2.webp'], 'is_new' => true, 'is_featured' => true],
        ['slug' => 'white-bud-vase', 'category' => 'vases', 'name_ar' => 'فازة بيضاء صغيرة', 'name_en' => 'Small White Bud Vase', 'price' => 35_000, 'stock' => 15, 'image' => 'p-white-vase.webp', 'material' => ['سيراميك', 'Ceramic'], 'color' => ['أبيض', 'White'], 'dimensions' => [10, 10, 20]],
        ['slug' => 'amber-glass-vase', 'category' => 'vases', 'name_ar' => 'فازة زجاج عنبري', 'name_en' => 'Amber Glass Vase', 'price' => 55_000, 'stock' => 10, 'image' => 'p-amber-vase.webp', 'material' => ['زجاج', 'Glass'], 'color' => ['عنبري', 'Amber'], 'dimensions' => [20, 20, 30], 'is_new' => true],
        ['slug' => 'gold-silk-rug', 'category' => 'accessories', 'name_ar' => 'سجادة حرير بنقشة ذهبية', 'name_en' => 'Gold Pattern Silk Rug', 'price' => 520_000, 'stock' => 10, 'image' => 'p-silk-rug.webp', 'material' => ['حرير', 'Silk'], 'color' => ['ذهبي', 'Gold'], 'dimensions' => [200, 300, 1]],
        ['slug' => 'still-life-flowers', 'category' => 'paintings', 'name_ar' => 'لوحة زهور كلاسيكية', 'name_en' => 'Classic Still-Life Flowers', 'price' => 175_000, 'stock' => 5, 'image' => 'p-flower-art.webp', 'material' => ['ألوان زيتية على قماش بإطار خشبي', 'Oil on canvas, wooden frame'], 'color' => ['متعدد الألوان', 'Multicolour'], 'dimensions' => [60, 3, 80], 'extra' => ['p-flower-art-detail-1.webp', 'p-flower-art-detail-2.webp'], 'is_new' => true, 'is_featured' => true],
        ['slug' => 'abstract-oil', 'category' => 'paintings', 'name_ar' => 'لوحة تجريدية بألوان زيتية', 'name_en' => 'Abstract Oil Painting', 'price' => 210_000, 'stock' => 5, 'image' => 'p-abstract-art.webp', 'material' => ['ألوان زيتية على قماش', 'Oil on canvas'], 'color' => ['متعدد الألوان', 'Multicolour'], 'dimensions' => [70, 3, 90]],
        ['slug' => 'gallery-frame-set', 'category' => 'paintings', 'name_ar' => 'مجموعة إطارات جدارية', 'name_en' => 'Gallery Wall Frame Set', 'price' => 160_000, 'stock' => 6, 'image' => 'p-frame-set.webp', 'material' => ['خشب وزجاج', 'Wood and glass'], 'color' => ['أسود', 'Black'], 'dimensions' => [120, 2, 90]],
        ['slug' => 'cream-boucle-armchair', 'category' => 'chairs', 'name_ar' => 'كرسي بوكليه كريمي', 'name_en' => 'Cream Bouclé Armchair', 'price' => 650_000, 'stock' => 10, 'image' => 'p-boucle-chair.webp', 'material' => ['بوكليه وخشب', 'Bouclé and wood'], 'color' => ['كريمي', 'Cream'], 'dimensions' => [80, 78, 76], 'extra' => ['p-boucle-chair-detail-1.webp', 'p-boucle-chair-detail-2.webp'], 'is_featured' => true],
        ['slug' => 'swivel-lounge-chair', 'category' => 'chairs', 'name_ar' => 'كرسي استرخاء دوّار', 'name_en' => 'Swivel Lounge Chair', 'price' => 720_000, 'stock' => 10, 'image' => 'p-lounge-chair.webp', 'material' => ['قماش ومعدن', 'Fabric and metal'], 'color' => ['بيج', 'Beige'], 'dimensions' => [75, 75, 85], 'is_new' => true],
        ['slug' => 'rattan-wood-chair', 'category' => 'chairs', 'name_ar' => 'كرسي خشب بظهر مفرغ', 'name_en' => 'Open-Back Wooden Chair', 'price' => 185_000, 'stock' => 12, 'image' => 'p-wood-chair.webp', 'material' => ['خشب صلب', 'Solid wood'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [45, 50, 80]],
        ['slug' => 'emerald-velvet-sofa', 'category' => 'furniture', 'name_ar' => 'كنبة مخمل زمردية', 'name_en' => 'Emerald Velvet Sofa', 'price' => 1_450_000, 'stock' => 10, 'image' => 'p-emerald-sofa.webp', 'material' => ['مخمل وخشب', 'Velvet and wood'], 'color' => ['زمردي', 'Emerald'], 'dimensions' => [220, 90, 80], 'extra' => ['p-emerald-sofa-detail-1.webp', 'p-emerald-sofa-detail-2.webp'], 'is_featured' => true],
        ['slug' => 'sand-three-seater', 'category' => 'furniture', 'name_ar' => 'كنبة رملية ثلاثية', 'name_en' => 'Sand Three-Seater Sofa', 'price' => 1_850_000, 'stock' => 10, 'image' => 'p-sand-sofa.webp', 'material' => ['قماش وخشب', 'Fabric and wood'], 'color' => ['رملي', 'Sand'], 'dimensions' => [240, 95, 82], 'extra' => ['p-sand-sofa-detail-1.webp', 'p-sand-sofa-detail-2.webp'], 'is_new' => true],
        ['slug' => 'royal-tufted-bed', 'category' => 'furniture', 'name_ar' => 'سرير كابيتونيه ملكي', 'name_en' => 'Royal Tufted Bed', 'price' => 2_350_000, 'stock' => 10, 'image' => 'p-tufted-bed.webp', 'material' => ['قماش مبطّن وخشب', 'Upholstered fabric and wood'], 'color' => ['بيج', 'Beige'], 'dimensions' => [190, 210, 120], 'extra' => ['p-tufted-bed-detail-1.webp', 'p-tufted-bed-detail-2.webp']],
        ['slug' => 'natural-oak-bed', 'category' => 'furniture', 'name_ar' => 'سرير خشب بلوط طبيعي', 'name_en' => 'Natural Oak Bed', 'price' => 1_650_000, 'stock' => 10, 'image' => 'p-oak-bed.webp', 'material' => ['خشب البلوط', 'Oak wood'], 'color' => ['طبيعي', 'Natural'], 'dimensions' => [170, 205, 95], 'extra' => ['p-oak-bed-detail-1.webp', 'p-oak-bed-detail-2.webp'], 'is_new' => true],
        // Low stock on purpose, so the red badge can be seen in the admin table.
        ['slug' => 'emerald-dining-set', 'category' => 'furniture', 'name_ar' => 'طقم سفرة زمردي 6 كراسي', 'name_en' => 'Emerald Dining Set, 6 Chairs', 'price' => 2_900_000, 'stock' => 2, 'image' => 'p-dining-set.webp', 'material' => ['خشب ومخمل', 'Wood and velvet'], 'color' => ['زمردي', 'Emerald'], 'dimensions' => [180, 90, 75], 'extra' => ['p-dining-set-detail-1.webp', 'p-dining-set-detail-2.webp']],
    ];

    /**
     * Reproduces the demo's sale prices, for example 1,450,000 down to 1,232,500.
     */
    private const OFFERS = [
        ['walnut-side-table', DiscountType::Percent, 20],
        ['gold-silk-rug', DiscountType::Percent, 20],
        ['abstract-oil', DiscountType::Percent, 20],
        ['rattan-console', DiscountType::Percent, 15],
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
            'link' => '/shop#sections', 'image' => 'hero-1.webp', 'image_mobile' => 'hero-1-m.webp',
        ],
        [
            'eyebrow_ar' => 'اختيارات جوز', 'eyebrow_en' => 'Joz Picks',
            'title_ar' => 'قطع اخترناها لك بعناية', 'title_en' => 'Pieces we picked for you',
            'subtitle_ar' => 'أجمل ما في المعرض هذا الموسم.', 'subtitle_en' => 'The best of the gallery this season.',
            'button_ar' => 'شاهد الاختيارات', 'button_en' => 'See the picks',
            'link' => '/joz-picks', 'image' => 'hero-2.webp', 'image_mobile' => 'hero-2-m.webp',
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
                'description_ar' => "{$data['name_ar']} من {$data['material'][0]}، باللون {$data['color'][0]}. قطعة مختارة من معرض جوز كاليري، تصلك إلى بابك في أي محافظة.",
                'description_en' => "{$data['name_en']} in {$data['material'][1]}, {$data['color'][1]}. Chosen for the Joz Gallery collection and delivered to your door in any governorate.",
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

            if ($product->getMedia('gallery')->isEmpty()) {
                foreach ([$data['image'], ...($data['extra'] ?? [])] as $file) {
                    $this->addImage($product, $file, 'gallery');
                }
            }

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

        $this->addImage($model, $file, $collection);
    }

    /**
     * @param  HasMedia&Model  $model
     */
    private function addImage(HasMedia $model, string $file, string $collection): void
    {
        $model->addMedia(database_path("seeders/demo/{$file}"))
            ->preservingOriginal()
            ->toMediaCollection($collection);
    }
}
