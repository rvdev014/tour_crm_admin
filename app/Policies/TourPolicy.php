<?php

namespace App\Policies;

use App\Models\Tour;
use App\Models\User;
use App\Services\TourService;
use Illuminate\Auth\Access\Response;

class TourPolicy
{
    public function update(User $user, Tour $tour): bool
    {
        return TourService::isVisible($tour);
    }

    public function delete(User $user, Tour $tour): bool
    {
        return TourService::isVisible($tour);
    }

    public function forceDelete(User $user, Tour $tour): bool
    {
        return TourService::isVisible($tour);
    }
}
