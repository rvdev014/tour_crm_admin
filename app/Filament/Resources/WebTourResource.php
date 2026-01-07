<?php

namespace App\Filament\Resources;

use App\Enums\WebTourPriceStatus;
use App\Enums\WebTourStatus;
use App\Filament\Resources\WebTourResource\Pages;
use App\Filament\Resources\WebTourResource\RelationManagers;
use App\Models\Package;
use App\Models\WebTour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\WebTourResource\Actions\PricesAction;

class WebTourResource extends Resource
{
    protected static ?string $model = WebTour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 1;
    
    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('name_ru')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('name_en')
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->options(WebTourStatus::class)
                        ->default(WebTourStatus::New),
                ]),

                Forms\Components\Grid::make(1)->schema([
                    Forms\Components\Toggle::make('is_popular')
                        ->label('Popular Tour')
                        ->default(false),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\DateTimePicker::make('start_date')
                        ->required(),
                    Forms\Components\DateTimePicker::make('end_date')
                        ->required(),
                    Forms\Components\DateTimePicker::make('deadline'),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Textarea::make('description_ru'),
                    Forms\Components\Textarea::make('description_en'),
                    Forms\Components\FileUpload::make('photo')
                        ->image(),
                ]),

                Forms\Components\Repeater::make('days')
                    ->relationship('days')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->schema([
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('place_name_ru')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('place_name_en')
                                ->maxLength(255),
                        ]),
//                        Forms\Components\DateTimePicker::make('date'),
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\FileUpload::make('photo')
                                ->image(),
                            Forms\Components\Select::make('facilities')
                                ->relationship('facilities', 'name_ru')
                                ->multiple()
                                ->preload(),
                        ]),
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\Textarea::make('description_ru')
                                ->required(),
                            Forms\Components\Textarea::make('description_en'),
                        ]),
                    ]),

                Forms\Components\Select::make('packagesIncluded')
                    ->relationship('packagesIncluded', 'name_ru')
                    ->multiple()
                    ->preload()
                    ->reactive()
                    ->pivotData(['is_include' => true])
                    ->options(function ($state, $get) {
                        return Package::query()
                            ->whereNotIn('id', $get('packagesNotIncluded'))
                            ->pluck('name_ru', 'id');
                    }),

                Forms\Components\Select::make('packagesNotIncluded')
                    ->relationship('packagesNotIncluded', 'name_ru')
                    ->multiple()
                    ->preload()
                    ->reactive()
                    ->pivotData(['is_include' => false])
                    ->options(function ($state, $get) {
                        return Package::query()
                            ->whereNotIn('id', $get('packagesIncluded'))
                            ->pluck('name_ru', 'id');
                    }),

                Forms\Components\Repeater::make('accommodations')
                    ->relationship('accommodations')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->schema([
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('header_ru')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('header_en')
                                ->maxLength(255),
                        ]),

                        Forms\Components\Grid::make()->schema([
                            Forms\Components\Textarea::make('description_ru'),
                            Forms\Components\Textarea::make('description_en'),
                        ]),

                        Forms\Components\Select::make('hotels')
                            ->relationship('hotels', 'name')
                            ->multiple()
                            ->preload()
                    ]),

                Forms\Components\Repeater::make('prices')
                    ->relationship('prices')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('from_date')
                                ->required(),
                            Forms\Components\DatePicker::make('to_date')
                                ->required(),
                            Forms\Components\DateTimePicker::make('deadline'),
                        ]),
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->numeric(),
                            Forms\Components\Select::make('status')
                                ->options(WebTourPriceStatus::class)
                        ]),
                    ]),

                Forms\Components\Select::make('similarTours')
                    ->relationship('similarTours', 'name_ru')
                    ->multiple()
                    ->preload()
                    ->options(function ($state, $get, $record) {
                        $ignoreIds = $get('similarTours') ?: [];
                        if ($record) {
                            $ignoreIds[] = $record->id;
                        }
                        return WebTour::query()
                            ->whereNotIn('id', $ignoreIds)
                            ->pluck('name_ru', 'id');
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('name_ru')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name_en')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prices')
                    ->label('Date and prices')
                    ->formatStateUsing(fn() => 'View prices')
                    ->html()
                    ->action(WebTourResource\Actions\PricesAction::make()),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                
                Tables\Columns\ImageColumn::make('photo')
                    ->height('60px'),
                Tables\Columns\ToggleColumn::make('is_popular')
                    ->label('Popular')
                    ->sortable(),
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
                //
            ])
//            ->recordUrl(null)
//            ->recordAction(PricesAction::class)
            ->actions([
                Tables\Actions\EditAction::make(),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebTours::route('/'),
            'create' => Pages\CreateWebTour::route('/create'),
            'edit' => Pages\EditWebTour::route('/{record}/edit'),
        ];
    }
}
