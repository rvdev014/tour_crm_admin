<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_let_code(): void
    {
        $numbers = [5, 2, 8, 1, 3];
        $count = count($numbers);

        // Bubble Sort Algorithm (Ascending Order)
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count - $i; $j++) {
                if ($numbers[$i] > $numbers[$i + 1]) {
                    $temp = $numbers[$i + 1];
                    $numbers[$i + 1] = $numbers[$i];
                    $numbers[$i] = $temp;
                    dd($numbers);
                }
            }
        }

        dd($numbers);
    }
}
