<?php

namespace App\Filament\Resources;

use App\Enums\WebTourStatus;
use App\Enums\WebTourType;
use App\Filament\Resources\WebTourRequestResource\Pages;
use App\Models\WebTourRequest;
use App\Models\User;
use App\Models\WebTour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebTourRequestResource extends Resource
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
    protected static ?string $model = WebTourRequest::class;
    
    protected static ?string $label = 'Web Tour Requests';
    protected static ?string $pluralLabel = 'Web Tour Requests';
    
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 3;
    
    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::where('status', WebTourStatus::New->value)->count();
        return $count > 0 ? (string)$count : null;
    }
    
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                    
                Forms\Components\Select::make('web_tour_id')
                    ->label(__('Web Tour'))
                    ->relationship('webTour', 'name_en')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                    
                Forms\Components\TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('citizenship')
                    ->label(__('Citizenship'))
                    ->maxLength(255),
                    
                Forms\Components\Textarea::make('comment')
                    ->label(__('Comment'))
                    ->rows(3)
                    ->columnSpanFull(),
                    
                Forms\Components\TextInput::make('travellers_count')
                    ->label(__('Travellers Count'))
                    ->numeric()
                    ->minValue(1),
                    
                Forms\Components\Select::make('tour_type')
                    ->label(__('Tour Type'))
                    ->options([
                        WebTourType::Small->value => WebTourType::Small->getLabel(),
                        WebTourType::Private->value => WebTourType::Private->getLabel(),
                        WebTourType::Custom->value => WebTourType::Custom->getLabel(),
                    ])
                    ->nullable(),
                    
                Forms\Components\DatePicker::make('start_date')
                    ->label(__('Start Date'))
                    ->required(),
                    
                Forms\Components\Select::make('status')
                    ->label(__('Status'))
                    ->options([
                        WebTourStatus::New->value => WebTourStatus::New->getLabel(),
                        WebTourStatus::Waiting->value => WebTourStatus::Waiting->getLabel(),
                        WebTourStatus::Done->value => WebTourStatus::Done->getLabel(),
                        WebTourStatus::Rejected->value => WebTourStatus::Rejected->getLabel(),
                    ])
                    ->default(WebTourStatus::New->value)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('web_tour_id')
                    ->label(__('Web Tour'))
                    ->relationship('webTour', 'name_en')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        WebTourStatus::New->value => WebTourStatus::New->getLabel(),
                        WebTourStatus::Waiting->value => WebTourStatus::Waiting->getLabel(),
                        WebTourStatus::Done->value => WebTourStatus::Done->getLabel(),
                        WebTourStatus::Rejected->value => WebTourStatus::Rejected->getLabel(),
                    ]),
                
                Tables\Filters\SelectFilter::make('tour_type')
                    ->label(__('Tour Type'))
                    ->options([
                        WebTourType::Small->value => WebTourType::Small->getLabel(),
                        WebTourType::Private->value => WebTourType::Private->getLabel(),
                        WebTourType::Custom->value => WebTourType::Custom->getLabel(),
                    ]),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('User'))
                    ->getStateUsing(function (WebTourRequest $record) {
                        $link = "/admin/users/$record->user_id/edit";
                        return "<a href='{$link}' target='_blank'>{$record->user->name} ({$record->user->email})</a>";
                    })
                    ->color('info')
                    ->html()
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('webTour.name_en')
                    ->label(__('Web Tour'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('No tour selected')),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->getStateUsing(fn(WebTourRequest $record) => $record->email ?? $record->user?->email)
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('citizenship')
                    ->label(__('Citizenship'))
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('travellers_count')
                    ->label(__('Travellers'))
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('tour_type')
                    ->label(__('Tour Type'))
                    ->formatStateUsing(fn (?WebTourType $state): string => $state?->getLabel() ?? 'Not specified')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn() => auth()->user()->isAdmin())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
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
            'index' => Pages\ListWebTourRequests::route('/'),
            'edit' => Pages\EditWebTourRequest::route('/{record}/edit'),
        ];
    }
}