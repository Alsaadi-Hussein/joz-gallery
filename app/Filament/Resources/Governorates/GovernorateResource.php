<?php

namespace App\Filament\Resources\Governorates;

use App\Filament\Resources\Governorates\Pages\ManageGovernorates;
use App\Models\Governorate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class GovernorateResource extends Resource
{
    protected static ?string $model = Governorate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return __('admin.governorate.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.governorate.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.store');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')
                ->label(__('admin.common.name_ar'))
                ->required()
                ->maxLength(255),
            TextInput::make('name_en')
                ->label(__('admin.common.name_en'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('admin.common.name_ar'))
                    ->searchable(['name_ar', 'name_en']),
                TextColumn::make('name_en')
                    ->label(__('admin.common.name_en')),
                TextInputColumn::make('delivery_fee')
                    ->label(__('admin.governorate.delivery_fee'))
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0', 'max:4294967295']),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGovernorates::route('/'),
        ];
    }
}
