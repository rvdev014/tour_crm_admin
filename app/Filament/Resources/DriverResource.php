<?php

namespace App\Filament\Resources;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentIcon;
use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\RelationManagers;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
//                Forms\Components\TextInput::make('car_number')
//                    ->maxLength(255),
//                Forms\Components\TextInput::make('car_model')
//                    ->maxLength(255),
                Forms\Components\TextInput::make('chat_id')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('car_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('car_model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('chat_id')
                    ->searchable(),
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
                    Tables\Actions\BulkAction::make('delete')
                        ->modalIcon(FilamentIcon::resolve('actions::delete-action.modal') ?? 'heroicon-o-trash')
                        ->icon(FilamentIcon::resolve('actions::delete-action') ?? 'heroicon-m-trash')
                        ->successNotificationTitle('Drivers were successfully deleted')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            try {
                                $records->each(fn (Model $record) => $record->delete());

                                Notification::make()
                                    ->title('Success')
                                    ->body('Drivers were successfully deleted.')
                                    ->success()
                                    ->send();

                            } catch (QueryException $e) {
                                if ($e->getCode() === '23503') { // Foreign key violation

                                    $drivers = Driver::query()->whereIn('id', $e->getBindings())->pluck('name')
                                        ->filter()
                                        ->map(fn ($name) => "'$name'")
                                        ->join(', ');

                                    Notification::make()
                                        ->title('Cannot delete some drivers')
                                        ->body("Cannot delete drivers: $drivers. They are used in tours.")
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title('Error')
                                    ->body('An error occurred while deleting.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
//                    Tables\Actions\DeleteBulkAction::make(),
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
