<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainWebPriceResource\Pages;
use App\Models\TrainWebPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Seated-train prices the website shows as "guaranteed tickets". Filled by Excel upload from
 * the Trains page; rows can also be corrected by hand here.
 */
class TrainWebPriceResource extends Resource
{
    // See TrainResource for why the navigation/breadcrumb/label getters are wrapped in __().
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    protected static ?string $model = TrainWebPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Train prices';

    protected static ?string $pluralModelLabel = 'Train prices';

    protected static ?string $navigationGroup = 'Website Content';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'train-web-prices';

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('train_id')
                ->relationship('train', 'name')
                ->native(false)
                ->required(),
            Forms\Components\Select::make('from_city_id')
                ->relationship('fromCity', 'name')
                ->native(false)
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('to_city_id')
                ->relationship('toCity', 'name')
                ->native(false)
                ->searchable()
                ->preload()
                ->required()
                ->different('from_city_id'),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('price_economy')->numeric()->minValue(0),
                Forms\Components\TextInput::make('price_business')->numeric()->minValue(0),
                Forms\Components\TextInput::make('price_vip')->numeric()->minValue(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['train', 'fromCity', 'toCity']))
            ->defaultSort('train_id')
            ->columns([
                Tables\Columns\TextColumn::make('train.name')->sortable(),
                Tables\Columns\TextColumn::make('fromCity.name')->label(__('From')),
                Tables\Columns\TextColumn::make('toCity.name')->label(__('To')),
                Tables\Columns\TextColumn::make('price_economy')->numeric(0, null, ' ')->placeholder('-'),
                Tables\Columns\TextColumn::make('price_business')->numeric(0, null, ' ')->placeholder('-'),
                Tables\Columns\TextColumn::make('price_vip')->numeric(0, null, ' ')->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('train_id')
                    ->label(__('Train'))
                    ->relationship('train', 'name'),
                Tables\Filters\SelectFilter::make('from_city_id')
                    ->label(__('From'))
                    ->relationship('fromCity', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No website prices yet'))
            ->emptyStateDescription(__('Upload an Excel file to publish train prices on the website.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainWebPrices::route('/'),
        ];
    }
}
