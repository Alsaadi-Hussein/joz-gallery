<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockProducts extends TableWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.low_stock'))
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->lowStock(Setting::lowStockThreshold())
                    ->with(['category', 'variants', 'media'])
            )
            ->stackedOnMobile()
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('admin.dashboard.low_stock_empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label(__('admin.product.image'))
                    ->collection('gallery')
                    ->conversion('thumb')
                    ->limit(1),
                TextColumn::make('name_ar')
                    ->label(__('admin.product.name')),
                TextColumn::make('category.name_ar')
                    ->label(__('admin.common.category')),
                TextColumn::make('effective_stock')
                    ->label(__('admin.product.stock'))
                    ->badge()
                    ->color('danger'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('admin.dashboard.update_stock'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
