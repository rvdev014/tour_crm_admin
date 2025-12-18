<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Models\HotelRule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


//[
//    'name' => '1. Стандарт (14:00 - 12:00)',
//    'checkIn' => '14:00',
//    'checkOut' => $nextDay . ' 12:00',
//],
//[
//    'name' => '2. Ранний до 6 утра (04:00 - 12:00)',
//    'checkIn' => '04:00',
//    'checkOut' => $nextDay . ' 12:00',
//],
//[
//    'name' => '3. Ранний обычный (09:00 - 12:00)',
//    'checkIn' => '09:00',
//    'checkOut' => $nextDay . ' 12:00',
//],
//[
//    'name' => '4. Поздний выезд 15:00 (3 часа лишних)',
//    'checkIn' => '14:00',
//    'checkOut' => $nextDay . ' 15:00',
//    // Логика: 15:00 - 12:00 = 3 часа. 3 * 10% = 0.3. Итого 1.3
//],
//[
//    'name' => '5. Грейс период (12:30)',
//    // Правило позднего выезда начинается с 13:00, значит 12:30 должно быть бесплатно
//    'checkIn' => '14:00',
//    'checkOut' => $nextDay . ' 12:30',
//],
//[
//    'name' => '6. КОМБО: Ранний (10:00) + Поздний (16:00)',
//    'checkIn' => '10:00',  // +0.5
//    'checkOut' => $nextDay . ' 16:00', // 16:00 - 12:00 = 4 часа * 0.1 = +0.4
//    // Итого: 1.0 (база) + 0.5 + 0.4 = 1.9
//],

class HotelRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';
    
    public function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('rule_type')
                            ->label('Rule Type')
                            // **IMPORTANT:** Replace this with your actual constants
                            ->options([
                                HotelRule::TYPE_EARLY_CHECK_IN => 'Early Check-in',
                                HotelRule::TYPE_LATE_CHECK_OUT => 'Late Check-out',
                            ])
                            ->required()
                            ->live(), // Enable "live" for conditional display of other fields
                    ])->columns(2),
                
                Forms\Components\Card::make('Time Parameters')
                    ->schema([
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time (from)')
                            ->seconds(false) // Optionally: remove seconds
                            ->nullable(),
                        
                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time (until)')
                            ->seconds(false)
                            ->nullable(),
                    ])->columns(2)
                    // Show the time block only for time-related rules
                    ->visible(fn(Forms\Get $get): bool => in_array($get('rule_type'), [
                        'early_check_in',
                        'late_check_out',
                    ])),
                
                Forms\Components\Section::make('Price Impact')
                    ->schema([
                        Forms\Components\Select::make('price_impact_type')
                            ->label('Price Impact Type')
                            // **IMPORTANT:** Replace this with your actual constants
                            ->options([
                                HotelRule::IMPACT_PERCENTAGE => 'Percentage',
                                HotelRule::IMPACT_HOURLY => 'Hourly',
                                HotelRule::IMPACT_FIXED => 'Fixed Amount',
                            ])
                            ->required()
                            ->live(), // Enable "live" for conditional display of impact_value
                        
                        Forms\Components\TextInput::make('impact_value')
                            ->label(fn(Forms\Get $get): string => match ($get('price_impact_type')) {
                                HotelRule::IMPACT_PERCENTAGE, HotelRule::IMPACT_HOURLY => 'Value (Percentage %)',
                                HotelRule::IMPACT_FIXED                                => 'Value (Amount)',
                                default                                                => 'Value',
                            })
                            ->numeric()
                            ->maxValue(
                                fn(Forms\Get $get): ?float => $get('price_impact_type') === 'percentage' ? 100 : null
                            )
                            ->placeholder('E.g., 50.00 or 1000')
                            ->required(fn(Forms\Get $get): bool => $get('price_impact_type') !== 'none')
                            ->visible(fn(Forms\Get $get): bool => $get('price_impact_type') !== 'none'),
                    ])->columns(2)
                    // Show the price block only for rules that affect the price
                    ->visible(fn(Forms\Get $get): bool => in_array($get('rule_type'), [
                        'early_check_in',
                        'late_check_out',
                        'cancellation',
                    ])),
                
                Forms\Components\Toggle::make('is_inclusive')
                    ->label('Is Included in Price?')
                    ->helperText('Check if this rule is already included in the base price/service.')
                    ->default(false),
            ]);
    }
    
    // TABLE Function with English Labels
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rule_type')
            ->columns([
                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Rule Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'early_check_in' => 'info',
                        'late_check_out' => 'warning',
                        'cancellation'   => 'danger',
                        default          => 'secondary',
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->dateTime('H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->dateTime('H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('impact_value')
                    ->label('Impact (Value)')
                    ->formatStateUsing(function(string $state, $record): string {
                        $symbol = $record->price_impact_type === 'percentage' ? '%' : ' $'; // Or another currency
                        return number_format($record->impact_value, 2) . $symbol;
                    })
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_inclusive')
                    ->label('Included')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn() => auth()->user()->isAdmin())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }
}
