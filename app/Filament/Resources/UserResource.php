<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'role'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Role' => $record->role === 0 ? 'Admin' : 'Operator'
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();//->where('id', '!=', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->suffixAction(function($record) {
                        if (!$record?->email) {
                            return [];
                        }
                        return [
                            Forms\Components\Actions\Action::make('hotel_email')
                                ->icon('heroicon-o-paper-airplane')
                                ->url("mailto:{$record->email}", true)
                        ];
                    }),
                Forms\Components\TextInput::make('password')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(UserRole::class)
                    ->required()
                    ->default(1),
                Forms\Components\TextInput::make('operator_percent_tps'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->url(fn($record) => $record->email ? "mailto:{$record->email}" : null, true)
                    ->color('info')
                    ->searchable()
                    ->html(),
                Tables\Columns\TextColumn::make('role')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operator_percent_tps')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                /*Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),*/
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
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function canCreate(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->isAdmin();
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
