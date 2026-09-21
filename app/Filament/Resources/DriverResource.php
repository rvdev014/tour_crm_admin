<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\RelationManagers;
use App\Enums\DriverRole;
use App\Models\Driver;
use App\Models\Transfer;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class DriverResource extends Resource
{

    // Sidebar label — Filament otherwise falls back to the auto-derived
    // English plural model name (e.g. "Hotels"), which never changes with
    // the panel's locale. See AppServiceProvider for the equivalent
    // ->translateLabel() hook covering field/column labels; this can't be
    // done the same way since getNavigationLabel() is called statically.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    // See the comment on getNavigationLabel() above / AdminPanelProvider's
    // navigationGroups() — Filament matches resources to their registered
    // group by comparing this value against the group's getLabel(), so both
    // sides need translating the same way or the match silently fails.
    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    // Breadcrumb text ("X > List" above the page heading) — a third, separate
    // label pipeline from getNavigationLabel()/getNavigationGroup() above
    // (falls back to getTitleCasePluralModelLabel(), not either of those).
    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    // Plural model label — feeds table empty states ("Не найдено tours") and
    // some page headings. Singular getModelLabel() is deliberately NOT
    // overridden; see the class-level comment above the other nav overrides.
    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'chat_id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Phone' => $record->phone,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label(__('Role'))
                    ->options(DriverRole::class)
                    ->default(DriverRole::Driver->value)
                    ->required()
                    ->native(false)
                    ->helperText(__('A dispatcher sees every transfer in the cabinet and can set any status. They are not offered as a driver on trips.'))
                    // Turning a driver into a dispatcher while they are still on upcoming trips would leave
                    // them in driver_ids but missing from the "Driver supplier" picker (which lists drivers
                    // only), and their Telegram reminders would keep firing. Make the operator reassign first.
                    ->rules(fn (?Model $record) => [
                        function (string $attribute, mixed $value, Closure $fail) use ($record) {
                            $becomingDispatcher = $value === DriverRole::Dispatcher->value;

                            if ($record === null || ! $becomingDispatcher || $record->role === DriverRole::Dispatcher) {
                                return;
                            }

                            $upcoming = Transfer::query()
                                ->where('date_time', '>=', Carbon::now('Asia/Tashkent')->startOfDay())
                                ->whereJsonContains('driver_ids', (string) $record->getKey())
                                ->count();

                            if ($upcoming > 0) {
                                $fail(__('This driver is still assigned to :count upcoming transfers. Reassign them first.', ['count' => $upcoming]));
                            }
                        },
                    ]),
                /*Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),*/
                PhoneInput::make('phone')
                    ->strictMode()
                    ->onlyCountries(['UZ'])
                    ->defaultCountry('UZ')
                    // The phone is also the driver's cabinet login. Two spellings of one number
                    // ("90 111 22 33" / "+998901112233") must not become two drivers, so uniqueness is
                    // checked on the NORMALIZED value — the DB's partial unique index would otherwise
                    // surface as a raw 500 on save.
                    ->rules(fn (?Model $record) => [
                        function (string $attribute, mixed $value, Closure $fail) use ($record) {
                            $normalized = PhoneNormalizer::uz($value);

                            if ($normalized === null) {
                                return;
                            }

                            $taken = Driver::query()
                                ->where('phone_normalized', $normalized)
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail(__('This phone number is already used by another driver.'));
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('car_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('car_model')
                    ->maxLength(255),
                Forms\Components\TextInput::make('chat_id')
                    ->maxLength(255),

                Forms\Components\Section::make(__('Cabinet access'))
                    ->description(__('Both drivers and dispatchers sign in with the phone number and this password.'))
                    ->icon('heroicon-o-key')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Cabinet access enabled'))
                            ->default(true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->minLength(6)
                            ->maxLength(255)
                            ->autocomplete('new-password')
                            // Blank means "leave unchanged" (and, on create, "no cabinet access yet").
                            // Driver's `hashed` cast hashes whatever is written.
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(__('Leave empty to keep the current password')),
                        Forms\Components\Placeholder::make('cabinet_url')
                            ->label(__('Login page'))
                            ->content(fn () => route('driver.login')),
                        Forms\Components\Placeholder::make('last_login_at')
                            ->label(__('Last login'))
                            ->content(fn (?Model $record) => $record?->last_login_at?->diffForHumans() ?? '—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('car_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('car_model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('chat_id')
                    ->searchable(),
                // Can this driver log in right now? Needs a password AND an enabled account.
                Tables\Columns\IconColumn::make('cabinet')
                    ->label(__('Cabinet'))
                    ->boolean()
                    ->getStateUsing(fn (Driver $record) => filled($record->password) && $record->is_active),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Last login'))
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label(__('Role'))
                    ->options(DriverRole::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_password')
                    ->label(__('Generate password'))
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('Generate a new password?'))
                    ->modalDescription(__('The current password stops working immediately. The new one is shown only once — copy it right away.'))
                    ->action(function (Driver $record) {
                        // The login is the normalized phone; without a usable one the password would be useless.
                        if (PhoneNormalizer::uz($record->phone) === null) {
                            Notification::make()
                                ->title(__('Set a valid phone number first'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $password = Str::password(8, symbols: false);
                        $record->update(['password' => $password]);

                        // Shown once, in the session flash — never stored, never written to the database
                        // (no sendToDatabase()). Only the hash is persisted.
                        Notification::make()
                            ->title(__('New password generated'))
                            ->body("{$record->phone} — {$password}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->modalIcon(FilamentIcon::resolve('actions::delete-action.modal') ?? 'heroicon-o-trash')
                        ->icon(FilamentIcon::resolve('actions::delete-action') ?? 'heroicon-m-trash')
                        ->successNotificationTitle('Drivers were successfully deleted')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn() => auth()->user()->isAdmin())
                        ->action(function ($records) {
                            try {
                                $records->each(fn(Model $record) => $record->delete());

                                Notification::make()
                                    ->title('Success')
                                    ->body('Drivers were successfully deleted.')
                                    ->success()
                                    ->send();
                            } catch (QueryException $e) {
                                if ($e->getCode() === '23503') { // Foreign key violation

                                    $drivers = Driver::query()->whereIn('id', $e->getBindings())->pluck('name')
                                        ->filter()
                                        ->map(fn($name) => "'$name'")
                                        ->join(', ');

                                    Notification::make()
                                        ->title('Cannot delete some drivers')
                                        ->body("Cannot delete drivers: $drivers. They are used in tours.")
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                Log::error("Bulk driver delete failed: {$e->getMessage()}", ['exception' => $e]);

                                Notification::make()
                                    ->title('Error')
                                    ->body('An error occurred while deleting.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
//                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
