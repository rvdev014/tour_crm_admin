<?php

namespace App\Filament\Resources;

use App\Enums\WagonClass;
use App\Enums\WebTourStatus;
use App\Filament\Resources\TrainRequestResource\Pages;
use App\Models\TrainRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainRequestResource extends Resource
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

    protected static ?string $model = TrainRequest::class;

    protected static ?string $label = 'Train Requests';

    protected static ?string $pluralLabel = 'Train Requests';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Website Management';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::where('status', WebTourStatus::New->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn () => auth()->user()->isOperator())
            ->schema([
                Forms\Components\TextInput::make('from')
                    ->label(__('From'))
                    ->required(),

                Forms\Components\TextInput::make('to')
                    ->label(__('To'))
                    ->required(),

                Forms\Components\DatePicker::make('departure_date')
                    ->label(__('Departure Date'))
                    ->required(),

                Forms\Components\DatePicker::make('return_date')
                    ->label(__('Return Date'))
                    ->minDate(fn ($get) => $get('departure_date')),

                Forms\Components\TextInput::make('passengers_count')
                    ->label(__('Passengers'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),

                Forms\Components\Select::make('wagon_class')
                    ->label(__('Wagon Class'))
                    ->native(false)
                    ->options(WagonClass::class),

                Forms\Components\Select::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\TextInput::make('phone')
                    ->label(__('Phone')),

                Forms\Components\TextInput::make('email')
                    ->label(__('Email'))
                    ->email(),

                Forms\Components\Select::make('status')
                    ->label(__('Status'))
                    ->options(WebTourStatus::class)
                    ->required(),

                Forms\Components\Textarea::make('comment')
                    ->label(__('Comments'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('from')
                    ->label(__('From'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('to')
                    ->label(__('To'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('departure_date')
                    ->label(__('Departure Date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('return_date')
                    ->label(__('Return Date'))
                    ->date()
                    ->placeholder(__('One-way'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('passengers_count')
                    ->label(__('Passengers'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('wagon_class')
                    ->label(__('Wagon Class'))
                    ->badge(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('Guest')),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone')),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email')),

                Tables\Columns\TextColumn::make('comment')
                    ->label(__('Comments'))
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\SelectColumn::make('status')
                    ->label(__('Status'))
                    ->options(WebTourStatus::class)
                    ->disabled(fn () => auth()->user()->isOperator())
                    ->afterStateUpdated(function ($record) {
                        $record->update(['status_updated_by' => auth()->id()]);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(WebTourStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn () => auth()->user()->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()->isAdmin()),
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
            'index' => Pages\ListTrainRequests::route('/'),
            'edit' => Pages\EditTrainRequest::route('/{record}/edit'),
        ];
    }
}
