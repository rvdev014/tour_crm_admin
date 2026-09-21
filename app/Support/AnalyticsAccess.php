<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may open the transfer analytics page and download its Excel file. One rule, used by the page AND the
 * download route, so the two can never disagree.
 *
 * The report shows all revenue, costs and profit: Admin and Accountant only. Operators keep seeing just their
 * own transfers, as before.
 */
final class AnalyticsAccess
{
    public static function allows(mixed $user): bool
    {
        return $user instanceof User && ($user->isAdmin() || $user->isAccountant());
    }
}
