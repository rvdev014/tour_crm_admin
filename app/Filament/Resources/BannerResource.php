<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Filament\Resources\BannerResource\RelationManagers;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('header_ru')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('header_en')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description_ru')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_en')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('link')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('photo')
                    ->image()
//                    ->formatStateUsing(fn($state) => dd($state))
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('header_ru')
                    ->searchable(),
                Tables\Columns\TextColumn::make('header_en')
                    ->searchable(),
                Tables\Columns\TextColumn::make('link')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('photo')
                    ->height('40px'),
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
