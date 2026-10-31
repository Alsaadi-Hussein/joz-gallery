<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PriceService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $lowStockThreshold = Setting::lowStockThreshold();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'variants', 'media']))
            ->stackedOnMobile()
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label(__('admin.product.image'))
                    ->collection('gallery')
                    ->conversion('thumb')
                    ->limit(1),
                TextColumn::make('name_ar')
                    ->label(__('admin.product.name'))
                    ->description(fn (Product $record): string => $record->name_en)
                    ->searchable(['name_ar', 'name_en', 'sku'])
                    ->sortable(),
                TextColumn::make('category.name_ar')
                    ->label(__('admin.common.category'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('admin.product.price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('final_price')
                    ->label(__('admin.product.final_price'))
                    ->state(fn (Product $record): int => app(PriceService::class)->finalPrice($record))
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('effective_stock')
                    ->label(__('admin.product.stock'))
                    ->badge()
                    ->color(fn (int $state): string => $state <= $lowStockThreshold ? 'danger' : 'success'),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('admin.common.category'))
                    ->relationship('category', 'name_ar')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),
                Filter::make('low_stock')
                    ->label(__('admin.product.low_stock'))
                    ->query(fn (Builder $query): Builder => $query->lowStock($lowStockThreshold)),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('replicate')
                    ->label(__('admin.product.replicate'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.product.replicate_hint'))
                    ->authorize('replicate')
                    ->successNotificationTitle(__('admin.product.replicated'))
                    ->action(function (Product $record, Action $action): void {
                        $copy = $record->duplicate();

                        $action->successRedirectUrl(ProductResource::getUrl('edit', ['record' => $copy]));
                        $action->success();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('move_to_category')
                        ->label(__('admin.product.move_to_category'))
                        ->icon(Heroicon::OutlinedFolderArrowDown)
                        ->schema([
                            Select::make('category_id')
                                ->label(__('admin.common.category'))
                                ->relationship('category', 'name_ar')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->successNotificationTitle(__('admin.product.moved'))
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data, BulkAction $action): void {
                            $records->each(fn (Product $product) => $product->update(['category_id' => $data['category_id']]));
                            $action->success();
                        }),
                    BulkAction::make('hide')
                        ->label(__('admin.product.hide'))
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->requiresConfirmation()
                        ->successNotificationTitle(__('admin.product.hidden'))
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, BulkAction $action): void {
                            $records->each(fn (Product $product) => $product->update(['is_active' => false]));
                            $action->success();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
