<?php

namespace App\Filament\Resources\ContactRequestResource\Pages;

use App\Enums\WebTourStatus;
use App\Filament\Resources\ContactRequestResource;
use App\Models\ContactRequest;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    public function getTabs(): array
    {
        $count = fn(array $statuses) => ContactRequest::query()->whereIn('status', $statuses)->count();

        $new = [WebTourStatus::New->value];
        $inProcess = [WebTourStatus::Waiting->value];
        // Rejected is a legacy status; those rows read as "Done".
        $done = [WebTourStatus::Done->value, WebTourStatus::Rejected->value];

        return [
            'new' => Tab::make(__('New'))
                ->badge($count($new))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', $new)),
            'in_process' => Tab::make(__('In process'))
                ->badge($count($inProcess))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', $inProcess)),
            'done' => Tab::make(__('Done'))
                ->badge($count($done))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', $done)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }
}
