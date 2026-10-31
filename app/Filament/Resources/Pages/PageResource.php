<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    private const TOOLBAR = [['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['undo', 'redo']];

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title_ar';

    public static function getModelLabel(): string
    {
        return __('admin.page.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.page.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title_ar')
                        ->label(__('admin.page.title_ar'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('title_en')
                        ->label(__('admin.page.title_en'))
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
                        // The header and footer link to these pages by their address.
                        ->disabled(fn (?Page $record): bool => $record?->isCore() ?? false)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Toggle::make('is_active')
                        ->label(__('admin.common.is_active'))
                        ->default(true)
                        ->inline(false),
                ]),
            RichEditor::make('body_ar')
                ->label(__('admin.page.body_ar'))
                ->toolbarButtons(self::TOOLBAR)
                ->columnSpanFull(),
            RichEditor::make('body_en')
                ->label(__('admin.page.body_en'))
                ->toolbarButtons(self::TOOLBAR)
                ->columnSpanFull(),
            Textarea::make('meta_description')
                ->label(__('admin.product.meta_description'))
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_ar')
                    ->label(__('admin.page.title_ar'))
                    ->description(fn (Page $record): string => $record->title_en)
                    ->searchable(['title_ar', 'title_en']),
                TextColumn::make('slug')
                    ->label(__('admin.common.slug')),
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
