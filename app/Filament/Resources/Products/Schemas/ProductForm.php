<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\ImageUpload;
use App\Models\Product;
use App\Services\PriceService;
use App\Support\Money;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    private const MAX_UNSIGNED_INT = 4_294_967_295;

    private const MAX_CM = 65_535;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('product')
                ->columnSpanFull()
                ->tabs([
                    self::infoTab(),
                    self::imagesTab(),
                    self::pricingTab(),
                    self::variantsTab(),
                    self::visibilityTab(),
                ]),
        ]);
    }

    private static function infoTab(): Tab
    {
        return Tab::make(__('admin.product.tabs.info'))
            ->columns(2)
            ->schema([
                TextInput::make('name_ar')
                    ->label(__('admin.common.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label(__('admin.common.name_en'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->live(onBlur: true)
                    // Create only: changing a live product's slug would break links already shared.
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                Select::make('category_id')
                    ->label(__('admin.common.category'))
                    ->relationship('category', 'name_ar')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('slug')
                    ->label(__('admin.common.slug'))
                    ->helperText(__('admin.common.slug_hint'))
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Textarea::make('description_ar')
                    ->label(__('admin.product.description_ar'))
                    ->rows(5),
                Textarea::make('description_en')
                    ->label(__('admin.product.description_en'))
                    ->rows(5)
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('material_ar')
                    ->label(__('admin.product.material_ar'))
                    ->maxLength(255),
                TextInput::make('material_en')
                    ->label(__('admin.product.material_en'))
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('color_ar')
                    ->label(__('admin.product.color_ar'))
                    ->maxLength(255),
                TextInput::make('color_en')
                    ->label(__('admin.product.color_en'))
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        self::dimensionInput('width_cm'),
                        self::dimensionInput('depth_cm'),
                        self::dimensionInput('height_cm'),
                    ]),
            ]);
    }

    private static function dimensionInput(string $name): TextInput
    {
        return TextInput::make($name)
            ->label(__("admin.product.{$name}"))
            ->integer()
            ->minValue(0)
            ->maxValue(self::MAX_CM)
            ->suffix(__('admin.common.cm'));
    }

    private static function imagesTab(): Tab
    {
        return Tab::make(__('admin.product.tabs.images'))
            ->schema([
                ImageUpload::configure(SpatieMediaLibraryFileUpload::make('gallery'))
                    ->label(__('admin.product.gallery'))
                    ->helperText(__('admin.product.gallery_hint'))
                    ->collection('gallery')
                    ->conversion('thumb')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->maxFiles(10)
                    ->panelLayout('grid')
                    ->required(),
            ]);
    }

    private static function pricingTab(): Tab
    {
        return Tab::make(__('admin.product.tabs.pricing'))
            ->columns(2)
            ->schema([
                TextInput::make('price')
                    ->label(__('admin.product.price'))
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(self::MAX_UNSIGNED_INT)
                    ->suffix(__('admin.common.currency'))
                    ->live(onBlur: true),
                TextEntry::make('final_price')
                    ->label(__('admin.product.final_price'))
                    ->helperText(__('admin.product.final_price_hint'))
                    ->state(fn (?Product $record, Get $get): string => self::finalPrice($record, $get)),
                TextInput::make('stock')
                    ->label(__('admin.product.stock'))
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(self::MAX_UNSIGNED_INT)
                    ->default(0)
                    ->disabled(fn (Get $get): bool => filled($get('variants')))
                    ->helperText(fn (Get $get): ?string => filled($get('variants')) ? __('admin.product.stock_from_variants') : null),
                TextInput::make('sku')
                    ->label(__('admin.product.sku'))
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['dir' => 'ltr']),
            ]);
    }

    /**
     * Prices what is typed in the form, not what is saved, so the figure follows the
     * price and category fields. Works on a copy to leave the form's record untouched.
     */
    private static function finalPrice(?Product $record, Get $get): string
    {
        $product = $record ? clone $record : new Product;
        $product->price = (int) $get('price');
        $product->category_id = $get('category_id') ? (int) $get('category_id') : null;

        return Money::format(app(PriceService::class)->finalPrice($product));
    }

    private static function variantsTab(): Tab
    {
        return Tab::make(__('admin.product.tabs.variants'))
            ->schema([
                Repeater::make('variants')
                    ->label(__('admin.product.variants'))
                    ->relationship()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel(__('admin.product.add_variant'))
                    ->columns(5)
                    ->schema([
                        TextInput::make('color_ar')
                            ->label(__('admin.product.color_ar'))
                            ->maxLength(255),
                        TextInput::make('color_en')
                            ->label(__('admin.product.color_en'))
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('size')
                            ->label(__('admin.product.size'))
                            ->maxLength(255),
                        TextInput::make('price_delta')
                            ->label(__('admin.product.price_delta'))
                            ->hint(__('admin.product.price_delta_hint'))
                            ->required()
                            ->integer()
                            ->default(0)
                            ->suffix(__('admin.common.currency')),
                        TextInput::make('stock')
                            ->label(__('admin.product.stock'))
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(self::MAX_UNSIGNED_INT)
                            ->default(0),
                    ]),
            ]);
    }

    private static function visibilityTab(): Tab
    {
        return Tab::make(__('admin.product.tabs.visibility'))
            ->schema([
                Grid::make(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('admin.product.is_active'))
                            ->default(true),
                        Toggle::make('is_new')
                            ->label(__('admin.product.is_new')),
                        Toggle::make('is_featured')
                            ->label(__('admin.product.is_featured')),
                    ]),
                TextInput::make('meta_title')
                    ->label(__('admin.product.meta_title'))
                    ->maxLength(255),
                Textarea::make('meta_description')
                    ->label(__('admin.product.meta_description'))
                    ->rows(3)
                    ->maxLength(300),
            ]);
    }
}
