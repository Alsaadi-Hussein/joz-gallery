<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parent', 'media'])->withCount('products'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('admin.common.image'))
                    ->collection('image')
                    ->conversion('thumb'),
                TextColumn::make('name_ar')
                    ->label(__('admin.product.name'))
                    ->description(fn (Category $record): string => $record->name_en)
                    ->formatStateUsing(fn (string $state, Category $record): string => $record->parent_id ? "— {$state}" : $state)
                    ->searchable(['name_ar', 'name_en']),
                TextColumn::make('parent.name_ar')
                    ->label(__('admin.category.parent')),
                TextColumn::make('products_count')
                    ->label(__('admin.category.products_count'))
                    ->badge()
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Skips categories that still have products or children.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
