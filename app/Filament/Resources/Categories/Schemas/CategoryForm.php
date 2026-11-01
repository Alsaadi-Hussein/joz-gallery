<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\ImageUpload;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
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
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('admin.common.slug'))
                    ->helperText(__('admin.common.slug_hint'))
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Select::make('parent_id')
                    ->label(__('admin.category.parent'))
                    ->helperText(__('admin.category.parent_hint'))
                    // A category cannot sit under itself or under one of its own children.
                    ->relationship(
                        'parent',
                        'name_ar',
                        fn (Builder $query, ?Category $record): Builder => $record
                            ? $query->whereKeyNot([$record->id, ...$record->descendantIds()])
                            : $query,
                    )
                    ->searchable()
                    ->preload(),
                ImageUpload::configure(SpatieMediaLibraryFileUpload::make('image'))
                    ->label(__('admin.common.image'))
                    ->collection('image')
                    ->conversion('thumb'),
                Toggle::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->default(true),
            ]);
    }
}
