<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Support\ImageUpload;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title_ar';

    public static function getModelLabel(): string
    {
        return __('admin.banner.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.banner.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.banner.images'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    self::imageUpload('image')
                        ->label(__('admin.banner.image'))
                        ->helperText(__('admin.banner.image_hint'))
                        ->required(),
                    self::imageUpload('image_mobile')
                        ->label(__('admin.banner.image_mobile'))
                        ->helperText(__('admin.banner.image_mobile_hint')),
                ]),
            Section::make(__('admin.banner.texts'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title_ar')->label(__('admin.banner.title_ar'))->required()->maxLength(255),
                    TextInput::make('title_en')->label(__('admin.banner.title_en'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('eyebrow_ar')->label(__('admin.banner.eyebrow_ar'))->maxLength(255),
                    TextInput::make('eyebrow_en')->label(__('admin.banner.eyebrow_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('subtitle_ar')->label(__('admin.banner.subtitle_ar'))->maxLength(255),
                    TextInput::make('subtitle_en')->label(__('admin.banner.subtitle_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('button_ar')->label(__('admin.banner.button_ar'))->maxLength(255),
                    TextInput::make('button_en')->label(__('admin.banner.button_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                ]),
            TextInput::make('link')
                ->label(__('admin.banner.link'))
                ->helperText(__('admin.banner.link_hint'))
                ->regex('#^(/|https?://)#')
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr']),
            Toggle::make('is_active')
                ->label(__('admin.common.is_active'))
                ->default(true),
        ]);
    }

    private static function imageUpload(string $collection): SpatieMediaLibraryFileUpload
    {
        return ImageUpload::configure(SpatieMediaLibraryFileUpload::make($collection))
            ->collection($collection)
            ->conversion('large');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('media'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('admin.common.image'))
                    ->collection('image')
                    ->conversion('large'),
                TextColumn::make('title_ar')
                    ->label(__('admin.banner.title_ar'))
                    ->description(fn (Banner $record): string => $record->title_en)
                    ->searchable(['title_ar', 'title_en']),
                TextColumn::make('link')
                    ->label(__('admin.banner.link')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
