<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomTypeResource\Pages;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('picture')
                            ->label('Picture')
                            ->image()
                            ->disk('public')
                            ->directory('room-types')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('picture')
                    ->label('Picture')
                    ->disk('public')
                    ->size(60),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('picture')
                            ->label('Picture')
                            ->image()
                            ->disk('public')
                            ->directory('room-types')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Model $record) {
                        if (!$record->canBeDeleted()) {
                            $errorMessage = $record->getDeleteErrorMessage();
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Room Type')
                                ->body($errorMessage)
                                ->danger()
                                ->send();
                            
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            $undeletableRecords = [];
                            
                            foreach ($records as $record) {
                                if (!$record->canBeDeleted()) {
                                    $undeletableRecords[] = $record->name;
                                }
                            }
                            
                            if (!empty($undeletableRecords)) {
                                $count = count($undeletableRecords);
                                $recordsList = implode(', ', array_slice($undeletableRecords, 0, 5));
                                if ($count > 5) {
                                    $recordsList .= " and " . ($count - 5) . " more";
                                }
                                
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Delete Room Types')
                                    ->body("Cannot delete {$count} room type(s) ({$recordsList}) because they are being used by related records.")
                                    ->danger()
                                    ->send();
                                
                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoomTypes::route('/'),
        ];
    }
}
