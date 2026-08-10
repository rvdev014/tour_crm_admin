<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Tour;
use App\Models\City;
use App\Models\User;
use App\Models\Hotel;
use App\Enums\RateEnum;
use App\Models\Company;
use App\Models\Country;
use App\Enums\TourType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\CurrencyEnum;
use App\Services\TourService;
use Filament\Forms\Components;
use Illuminate\Support\Carbon;
use Filament\Resources\Resource;
use App\Tables\Columns\PeriodsColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\HotelSalesResource\Pages;

class HotelSalesResource extends Resource
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
    protected static ?string $model = Hotel::class;
    protected static ?string $label = 'Hotel Sales';
    
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;
    
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'inn', 'company_name', 'address', 'phones.phone_number'];
    }
    
    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
            
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                Tables\Filters\Filter::make('filters')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(['sm' => 2, 'md' => 4])->schema([
                            Components\Select::make('currency')
                                ->label(__('Currency'))
                                ->native(false)
                                ->formatStateUsing(fn() => CurrencyEnum::UZS->value)
                                ->options(CurrencyEnum::class),
                            Components\Select::make('company_id')
                                ->label(__('Company'))
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(Company::query()->pluck('name', 'id')->toArray()),
                            Components\Select::make('country_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(Country::all()->pluck('name', 'id')->toArray()),
                            Components\Select::make('city_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(fn($get) => TourService::getCities($get('country_id'))),
                        ])
                    ])
                    ->query(function(Builder $query, $data) {
                        return $query
                            ->when(
                                $data['country_id'],
                                fn($query, $countryId) => $query->where('country_id', $countryId)
                            )
                            ->when($data['city_id'], fn($query, $cityId) => $query->where('city_id', $cityId));
                    })
                    ->indicateUsing(function(array $data): array {
                        $indicators = [];
                        if ($companyId = $data['company_id']) {
                            $company = Company::query()->where('id', $companyId)->first();
                            $indicators['company_id'] = "Company: $company->name";
                        }
                        if ($data['country_id'] ?? null) {
                            $indicators['country_id'] = 'Country: ' . Country::find($data['country_id'])->name;
                        }
                        if ($data['city_id'] ?? null) {
                            $indicators['city_id'] = 'City: ' . City::find($data['city_id'])->name;
                        }
                        return $indicators;
                    })
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                
                PeriodsColumn::make('room_prices')
                    ->label(__('Room prices'))
                    ->getStateUsing(function($record, $livewire) {
                        $filters = $livewire->tableFilters;
                        
                        $group = null;
                        if ($companyId = $filters['filters']['company_id'] ?? null) {
                            /** @var Company $company */
                            $company = Company::query()->where('id', $companyId)->first();
                            $group = $company->group;
                        }
                        
                        return [
                            'hotel' => $record,
                            'isFirst' => $record->is($livewire->getTableRecords()->first()),
                            'group' => $group,
                            'currency' => $filters['filters']['currency'],
                        ];
                    }),
                
                Tables\Columns\TextColumn::make('email')
                    ->url(fn($record) => $record->email ? "mailto:{$record->email}" : null, true)
                    ->color('info')
                    ->searchable()
                    ->html(),
                Tables\Columns\TextColumn::make('inn')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label(__('Country'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label(__('City'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate')
                    ->label(__('Rate'))
                    ->getStateUsing(fn($record) => RateEnum::tryFrom($record->rate)?->getLabel())
                    ->sortable(),
            ])
            ->recordUrl(null)
            //            ->recordAction(HotelPeriodsAction::class)
            ->actions([
                //                Tables\Actions\EditAction::make(),
                //                HotelPeriodsAction::make()->label('')->icon(''),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)/*->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ])*/ ;
    }
    
    public static function canEdit(Model $record): bool
    {
        return false;
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotels::route('/'),
        ];
    }
}
