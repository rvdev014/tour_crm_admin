<?php

namespace Tests\Unit;

use App\Enums\DriverTransferStatus as S;
use PHPUnit\Framework\TestCase;

class DriverTransferStatusTest extends TestCase
{
    public function test_lifecycle_order_is_assigned_to_completed(): void
    {
        $this->assertSame(
            [S::Assigned, S::EnRouteToClient, S::WaitingForClient, S::OnTheWay, S::Completed],
            S::cases(),
        );
    }

    public function test_next_walks_forward_and_stops_at_completed(): void
    {
        $this->assertSame(S::EnRouteToClient, S::Assigned->next());
        $this->assertSame(S::WaitingForClient, S::EnRouteToClient->next());
        $this->assertSame(S::OnTheWay, S::WaitingForClient->next());
        $this->assertSame(S::Completed, S::OnTheWay->next());
        $this->assertNull(S::Completed->next());
    }

    public function test_only_the_immediate_next_status_is_allowed(): void
    {
        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                $this->assertSame(
                    $from->next() === $to,
                    $from->canAdvanceTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public function test_only_completed_is_final(): void
    {
        $this->assertTrue(S::Completed->isFinal());
        $this->assertFalse(S::OnTheWay->isFinal());
    }
}
