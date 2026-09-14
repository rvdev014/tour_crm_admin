<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferExtraResource\Pages;
use App\Models\TransferExtra;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransferExtraResource extends Resource
{
    // See TransportClassResource for why these four overrides exist —
    // Filament's nav label / nav group / breadcrumb / plural-label are
    // separate pipelines that each need their own __() wrap.
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

    protected static ?string $model = TransferExtra::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Website Management';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn () => auth()->user()->isOperator())
            ->schema([
                Forms\Components\TextInput::make('name_ru')
                    ->required()
                    ->maxLength(255)
                    ->label(__('Name (RU)')),
                Forms\Components\TextInput::make('name_en')
                    ->maxLength(255)
                    ->label(__('Name (EN)')),
                Forms\Components\Textarea::make('description_ru')
                    ->rows(2)
                    ->label(__('Description (RU)')),
                Forms\Components\Textarea::make('description_en')
                    ->rows(2)
                    ->label(__('Description (EN)')),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->prefix('$'),
                Forms\Components\TextInput::make('max_quantity')
                    ->required()
                    ->numeric()
                    ->default(5)
                    ->label(__('Max quantity')),
                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->label(__('Order')),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label(__('Active')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTransferExtras::route('/'),
        ];
    }
}
