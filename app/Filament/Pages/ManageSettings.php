<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Setting::class) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.store');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.settings.title');
    }

    public function mount(): void
    {
        $this->form->fill(Setting::query()->pluck('value', 'key')->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.settings.contact'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('store_phone')
                            ->label(__('admin.settings.store_phone'))
                            ->helperText(__('admin.settings.store_phone_hint'))
                            ->required()
                            ->regex('/^07\d{9}$/')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('whatsapp_number')
                            ->label(__('admin.settings.whatsapp_number'))
                            ->helperText(__('admin.settings.whatsapp_number_hint'))
                            ->required()
                            ->regex('/^964\d{10}$/')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('instagram')
                            ->label(__('admin.settings.instagram'))
                            ->helperText(__('admin.settings.instagram_hint'))
                            ->required()
                            ->regex('/^[A-Za-z0-9._]+$/')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('address')
                            ->label(__('admin.settings.address'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),
                Section::make(__('admin.settings.operations'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('low_stock_threshold')
                            ->label(__('admin.settings.low_stock_threshold'))
                            ->helperText(__('admin.settings.low_stock_threshold_hint'))
                            ->required()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('notification_email')
                            ->label(__('admin.settings.notification_email'))
                            ->email()
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('telegram_chat_id')
                            ->label(__('admin.settings.telegram_chat_id'))
                            ->regex('/^-?\d+$/')
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.settings.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()
            ->success()
            ->title(__('admin.settings.saved'))
            ->send();
    }
}
