<?php

namespace App\Filament\Pages;

use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Tour;
use App\Models\User;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Report extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.report';

    public function table(Table $table)
    {
        return $table
            ->query(Tour::query())
            ->striped()
            ->filters([
                Tables\Filters\Filter::make('country_id')
                    ->form([
                        Components\Select::make('country_id')
                            ->native(false)
                            ->relationship('country', 'name')
                            ->options(Country::all()->pluck('name', 'id')->toArray()),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->relationship('city', 'name')
                            ->options(fn($get) => TourService::getCities($get('country_id')))
                            ->preload(),
                        Components\Select::make('company_id')
                            ->native(false)
                            ->relationship('company', 'name')
                            ->options(Company::query()->pluck('name', 'id')->toArray()),
                        Components\Select::make('created_by')
                            ->label('Admin creator')
                            ->native(false)
                            ->relationship('createdBy', 'name')
                            ->options(User::query()->pluck('name', 'id')->toArray()),

                        Components\DatePicker::make('created_from'),
                        Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['country_id'],
                                fn($query, $countryId) => $query->where('country_id', $countryId)
                            )
                            ->when($data['city_id'], fn($query, $cityId) => $query->where('city_id', $cityId))
                            ->when(
                                $data['company_id'],
                                fn($query, $companyId) => $query->where('company_id', $companyId)
                            )
                            ->when(
                                $data['created_by'],
                                fn($query, $createdBy) => $query->where('created_by', $createdBy)
                            )
                            ->when(
                                $data['created_from'],
                                fn($query, $createdFrom) => $query->whereDate('created_at', '>=', $createdFrom)
                            )
                            ->when(
                                $data['created_until'],
                                fn($query, $createdUntil) => $query->whereDate('created_at', '<=', $createdUntil)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['country_id'] ?? null) {
                            $indicators['country_id'] = 'Country: ' . Country::find($data['country_id'])->name;
                        }
                        if ($data['city_id'] ?? null) {
                            $indicators['city_id'] = 'City: ' . City::find($data['city_id'])->name;
                        }
                        if ($data['company_id'] ?? null) {
                            $indicators['company_id'] = 'Company: ' . Company::find($data['company_id'])->name;
                        }
                        if ($data['created_by'] ?? null) {
                            $indicators['created_by'] = 'Admin creator: ' . User::find($data['created_by'])->name;
                        }
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse(
                                    $data['created_from']
                                )->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse(
                                    $data['created_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    })
            ])
            ->columns([
                Columns\TextColumn::make('group_number')
                    ->searchable(),
                Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('price')
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('expenses')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('income')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color(fn(Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('createdBy.name')
                    ->sortable(),
                Columns\TextColumn::make('createdBy.operator_percent_tps')
                    ->label('Operator %')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('country.name'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                /*Columns\TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),*/
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin())
                ]),
            ]);
    }
}
