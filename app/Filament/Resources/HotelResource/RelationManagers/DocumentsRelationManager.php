<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use App\Enums\AttachmentType;
use App\Models\Attachment;
use App\Services\FileService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Form $form): Form
    {
        return $form->disabled(fn () => auth()->user()->isOperator())
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Document')
                    ->required()
                    ->disk('public')
                    ->directory('hotel-documents')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 1).' KB' : '-'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['file_path'];

                        return FileService::createAttachmentFromFile($file, AttachmentType::Document, 'hotel-documents');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Attachment $record) => $record->getUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Attachment $record) => filled($record->getUrl())),
                Tables\Actions\DeleteAction::make()->authorize(fn () => auth()->user()->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()->isAdmin()),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
