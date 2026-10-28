<?php

namespace App\Services;

use App\Filament\Resources\ContactRequestResource;
use App\Filament\Resources\FlightRequestResource;
use App\Filament\Resources\HotelRequestResource;
use App\Filament\Resources\TrainRequestResource;
use App\Filament\Resources\TransferBookingResource;
use App\Filament\Resources\TransferRequestResource;
use App\Filament\Resources\WebTourRequestResource;

/**
 * What the topbar "new requests" pill and its alert read: for every kind of website request the
 * current user may open, how many still need attention and the id of the newest one.
 *
 * "Needs attention" is each model's scopeNeedsAttention() — the same definition the sidebar badges
 * and the red row highlight use — so the three can never disagree.
 */
class NewRequestsCounter
{
    /** @var list<class-string<\Filament\Resources\Resource>> */
    private const RESOURCES = [
        WebTourRequestResource::class,
        ContactRequestResource::class,
        HotelRequestResource::class,
        FlightRequestResource::class,
        TrainRequestResource::class,
        TransferRequestResource::class,
        TransferBookingResource::class,
    ];

    /**
     * @return list<array{key: string, label: string, singular: string, count: int, latest: int, url: string}>
     */
    public static function summary(): array
    {
        $rows = [];

        foreach (self::RESOURCES as $resource) {
            if (! $resource::canViewAny()) {
                continue;
            }

            $query = $resource::getModel()::query()->needsAttention();

            $rows[] = [
                'key' => $resource::getSlug(),
                'label' => $resource::getNavigationLabel(),
                'singular' => $resource::getModelLabel(),
                'count' => (clone $query)->count(),
                'latest' => (int) (clone $query)->max('id'),
                'url' => $resource::getUrl('index'),
            ];
        }

        return $rows;
    }

    public static function total(array $summary): int
    {
        return array_sum(array_column($summary, 'count'));
    }
}
