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
    protected static ?string $model = WebTourRequest::class;
    
    protected static ?string $label = 'Web Tour Requests';
    protected static ?string $pluralLabel = 'Web Tour Requests';
    
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                    
                Forms\Components\Select::make('web_tour_id')
                    ->label('Web Tour')
                    ->relationship('webTour', 'name_en')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                    
                Forms\Components\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('citizenship')
                    ->label('Citizenship')
                    ->maxLength(255),
                    
                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->rows(3)
                    ->columnSpanFull(),
                    
                Forms\Components\TextInput::make('travellers_count')
                    ->label('Travellers Count')
                    ->numeric()
                    ->minValue(1),
                    
                Forms\Components\Select::make('tour_type')
                    ->label('Tour Type')
                    ->options([
                        WebTourType::Small->value => WebTourType::Small->getLabel(),
                        WebTourType::Private->value => WebTourType::Private->getLabel(),
                        WebTourType::Custom->value => WebTourType::Custom->getLabel(),
                    ])
                    ->nullable(),
                    
                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),
                    
                Forms\Components\Select::make('status')
                    ->label('Status')
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
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('web_tour_id')
                    ->label('Web Tour')
                    ->relationship('webTour', 'name_en')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        WebTourStatus::New->value => WebTourStatus::New->getLabel(),
                        WebTourStatus::Waiting->value => WebTourStatus::Waiting->getLabel(),
                        WebTourStatus::Done->value => WebTourStatus::Done->getLabel(),
                        WebTourStatus::Rejected->value => WebTourStatus::Rejected->getLabel(),
                    ]),
                
                Tables\Filters\SelectFilter::make('tour_type')
                    ->label('Tour Type')
                    ->options([
                        WebTourType::Small->value => WebTourType::Small->getLabel(),
                        WebTourType::Private->value => WebTourType::Private->getLabel(),
                        WebTourType::Custom->value => WebTourType::Custom->getLabel(),
                    ]),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->getStateUsing(function (WebTourRequest $record) {
                        $link = "/admin/users/$record->user_id/edit";
                        return "<a href='{$link}' target='_blank'>{$record->user->name} ({$record->user->email})</a>";
                    })
                    ->color('info')
                    ->html()
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('webTour.name_en')
                    ->label('Web Tour')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No tour selected'),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('citizenship')
                    ->label('Citizenship')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('travellers_count')
                    ->label('Travellers')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('tour_type')
                    ->label('Tour Type')
                    ->formatStateUsing(fn (?WebTourType $state): string => $state?->getLabel() ?? 'Not specified')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (WebTourStatus $state): string => $state->getLabel())
                    ->colors([
                        'gray' => WebTourStatus::New,
                        'warning' => WebTourStatus::Waiting,
                        'success' => WebTourStatus::Done,
                        'danger' => WebTourStatus::Rejected,
                    ])
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListWebTourRequests::route('/'),
            'edit' => Pages\EditWebTourRequest::route('/{record}/edit'),
        ];
    }
}