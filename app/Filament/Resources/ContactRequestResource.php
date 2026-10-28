<?php

namespace App\Filament\Resources;

use App\Enums\WebTourStatus;
use App\Filament\Resources\ContactRequestResource\Pages;
use App\Filament\Resources\ContactRequestResource\RelationManagers;
use App\Models\ContactRequest;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ContactRequestResource extends Resource
{

    // Sidebar label — Filament otherwise falls back to the auto-derived
    // English plural model name (e.g. "Hotels"), which never changes with
    // the panel's locale. See AppServiceProvider for the equivalent
    // ->translateLabel() hook covering field/column labels; this can't be
    // done the same way since getNavigationLabel() is called statically.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    // See the comment on getNavigationLabel() above / AdminPanelProvider's
    // navigationGroups() — Filament matches resources to their registered
    // group by comparing this value against the group's getLabel(), so both
    // sides need translating the same way or the match silently fails.
    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    // Breadcrumb text ("X > List" above the page heading) — a third, separate
    // label pipeline from getNavigationLabel()/getNavigationGroup() above
    // (falls back to getTitleCasePluralModelLabel(), not either of those).
    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    // Plural model label — feeds table empty states ("Не найдено tours") and
    // some page headings. Singular getModelLabel() is deliberately NOT
    // overridden; see the class-level comment above the other nav overrides.
    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }
    protected static ?string $model = ContactRequest::class;
    protected static ?string $label = 'Contact Requests';

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationGroup = 'Website Requests';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::needsAttention()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    // Contact requests only use three states: New, In process, Done.
    // WebTourStatus is shared with other resources, so the mapping lives here:
    // Waiting reads as "In process", Rejected (legacy rows) reads as "Done".
    public static function statusLabel(WebTourStatus $status): string
    {
        return match ($status) {
            WebTourStatus::New => __('New'),
            WebTourStatus::Waiting => __('In process'),
            WebTourStatus::Done, WebTourStatus::Rejected => __('Done'),
        };
    }

    public static function mailto(ContactRequest $record): string
    {
        $quoted = collect(preg_split('/\R/', Str::limit($record->comment, 1500, '...')))
            ->map(fn($line) => '> ' . $line)
            ->implode("\n");

        $subject = rawurlencode("Re: your request — Let's Go Uzbekistan");
        $body = rawurlencode("Hello {$record->name},\n\n\n\n{$quoted}");

        return "mailto:{$record->email}?subject={$subject}&body={$body}";
    }

    public static function setStatus(ContactRequest $record, WebTourStatus $status): void
    {
        $record->update([
            'status'            => $status->value,
            'status_updated_by' => auth()->id(),
        ]);

        Notification::make()
            ->title(__('Status updated'))
            ->success()
            ->send();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')
                        ->label(__('Name')),
                    Infolists\Components\TextEntry::make('email')
                        ->label(__('Email'))
                        ->icon('heroicon-o-envelope')
                        ->url(fn(ContactRequest $record) => static::mailto($record)),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('Created'))
                        ->dateTime('d.m.Y H:i')
                        ->helperText(fn(ContactRequest $record) => $record->created_at->diffForHumans()),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->formatStateUsing(fn($state) => static::statusLabel($state)),
                    Infolists\Components\TextEntry::make('statusUpdatedBy.name')
                        ->label(__('Status by'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('user.name')
                        ->label(__('User'))
                        ->placeholder(__('Guest')),
                    Infolists\Components\TextEntry::make('comment')
                        ->label(__('Comment'))
                        ->columnSpanFull()
                        ->prose()
                        ->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn ($record) => $record->requiresAttention() ? 'ep-row-danger' : null)
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->recordUrl(fn(ContactRequest $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('d.m.Y H:i')
                    ->description(fn(ContactRequest $record) => $record->created_at->diffForHumans())
                    ->sortable()
                    ->description(fn ($record) => $record->created_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->url(fn(ContactRequest $record) => static::mailto($record)),
                Tables\Columns\TextColumn::make('comment')
                    ->searchable()
                    ->limit(80)
                    ->tooltip(fn($record) => $record->comment)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn($state) => static::statusLabel($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->label(__('Status by'))
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('set_new')
                        ->label(__('New'))
                        ->icon('heroicon-o-sparkles')
                        ->hidden(fn(ContactRequest $record) => $record->status === WebTourStatus::New)
                        ->action(fn(ContactRequest $record) => static::setStatus($record, WebTourStatus::New)),
                    Tables\Actions\Action::make('set_in_process')
                        ->label(__('In process'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->hidden(fn(ContactRequest $record) => $record->status === WebTourStatus::Waiting)
                        ->action(fn(ContactRequest $record) => static::setStatus($record, WebTourStatus::Waiting)),
                    Tables\Actions\Action::make('set_done')
                        ->label(__('Done'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->hidden(fn(ContactRequest $record) => $record->status === WebTourStatus::Done)
                        ->action(fn(ContactRequest $record) => static::setStatus($record, WebTourStatus::Done)),
                ])->label(__('Status')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
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
            'index' => Pages\ListContactRequests::route('/'),
            'view' => Pages\ViewContactRequest::route('/{record}'),
        ];
    }
}
