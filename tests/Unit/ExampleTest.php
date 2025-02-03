<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_basic_sort_array(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = rand(1, 10);
        }

        echo "\nUnsorted: " . implode(',', $numbers) . "\n\n";

        $result = $numbers;
        sort($result);

        $count = count($numbers);
        $iterates = 0;

        for ($i = 0; $i < ($count - 1); $i++) {
            for ($j = 0; $j < ($count - 1); $j++) {
                $iterates += 1;
                if ($numbers[$j] > $numbers[$j + 1]) {
                    $temp = $numbers[$j + 1];
                    $numbers[$j + 1] = $numbers[$j];
                    $numbers[$j] = $temp;
                }
            }
        }

        echo "Sorted: " . implode(',', $result) . "\n\n";
        echo "Iterates: " . $iterates . "\n";

        $this->assertEquals($result, $numbers);
    }

    public function test_optimized_sort_array_basic(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = rand(1, 10);
        }

        echo "\nUnsorted: " . implode(',', $numbers) . "\n\n";

        $result = $numbers;
        sort($result);

        $count = count($numbers);
        $iterates = 0;

        do {
            $swapped = false;

            for ($i = 0; $i < $count - 1; $i++) {
                if ($numbers[$i] > $numbers[$i + 1]) {
                    $iterates += 1;
                    $temp = $numbers[$i + 1];
                    $numbers[$i + 1] = $numbers[$i];
                    $numbers[$i] = $temp;

                    $swapped = true;
                }
            }
        } while ($swapped);

        echo "Sorted: " . implode(',', $result) . "\n\n";
        echo "Iterates: " . $iterates . "\n";

        $this->assertEquals($result, $numbers);
    }

    public function test_optimized_sort_array_reverse(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = rand(1, 10);
        }

        echo "\nUnsorted: " . implode(',', $numbers) . "\n\n";

        $result = $numbers;
        sort($result);

        $count = count($numbers);
        $iterates = 0;

        do {
            $swapped = false;

            for ($i = 0; $i < $count; $i++) {
                if ($i - 1 < 0) {
                    continue;
                }

                $prevI = $i - 1;
                if ($numbers[$prevI] > $numbers[$i]) {
                    $iterates += 1;
                    $temp = $numbers[$prevI];
                    $numbers[$prevI] = $numbers[$i];
                    $numbers[$i] = $temp;

                    $swapped = true;
                }
            }
        } while ($swapped);

        echo "Sorted: " . implode(',', $result) . "\n\n";
        echo "Iterates: " . $iterates . "\n";

        $this->assertEquals($result, $numbers);
    }

    /**
     * @dataProvider mergeProvider
     */
    public function test_merge($nums1, $m, $nums2, $n, $result): void
    {
        $rightIdx = $m + $n - 1;
        $mIdx = $m - 1;
        $nIdx = $n - 1;

        while ($nIdx >= 0) {
            if ($mIdx >= 0 && $nums1[$mIdx] > $nums2[$nIdx]) {
                $nums1[$rightIdx] = $nums1[$mIdx];
                $mIdx--;
            } else {
                $nums1[$rightIdx] = $nums2[$nIdx];
                $nIdx--;
            }
            $rightIdx--;
        }

        $this->assertEquals($result, $nums1);
    }

    public static function mergeProvider(): array
    {
        return [
            '1' => [[1, 2, 3, 0, 0, 0], 3, [2, 5, 6], 3, [1, 2, 2, 3, 5, 6]],
            '2' => [[1], 1, [], 0, [1]],
            '3' => [[0], 0, [1], 1, [1]],
        ];
    }

    /**
     * @dataProvider removeProvider
     */
    public function test_remove_element($nums, $rmElem, $expResult): void
    {
        $k = 0;
        for ($i = 0; $i < count($nums); $i++) {
            if ($nums[$i] !== $rmElem) {
                $nums[$k] = $nums[$i];
                $k++;
            }
        }

        $this->assertEquals($k, count($expResult));
        for ($i = 0; $i < $k; $i++) {
            $this->assertEquals($nums[$i], $expResult[$i]);
        }
    }

    public static function removeProvider(): array
    {
        return [
            '1' => [[3, 2, 2, 3], 3, [2, 2]],
            '2' => [[0, 1, 2, 2, 3, 0, 4, 2], 2, [0, 1, 3, 0, 4]]
        ];
    }

    /**
     * @dataProvider removeDuplicatesProvider
     */
    public function test_remove_duplicates($nums, $expResult): void
    {
        $k = 0;
        for ($i = 1; $i < count($nums); $i++) {
            if ($nums[$i] !== $nums[$k]) {
                $k++;
                $nums[$k] = $nums[$i];
            }
        }

        $this->assertEquals(count($expResult), $k + 1);
    }

    public static function removeDuplicatesProvider(): array
    {
        return [
            '1' => [[1, 1, 2], [1, 2]],
            '2' => [[0, 0, 1, 1, 1, 2, 2, 3, 3, 4], [0, 1, 2, 3, 4]],
        ];
    }

    /**
     * @dataProvider removeDuplicates2Provider
     */
    public function test_remove_duplicates_2($nums, $expResult): void
    {
        $k = 2;
        for ($i = 2; $i < count($nums); $i++) {
            if ($nums[$k - 2] != $nums[$i]) {
                $nums[$k] = $nums[$i];
                $k++;
            }
        }

        $this->assertEquals($k, count($expResult));
        for ($i = 0; $i < $k; $i++) {
            $this->assertEquals($nums[$i], $expResult[$i]);
        }
    }

    public static function removeDuplicates2Provider(): array
    {
        return [
            '1' => [[1, 1, 1, 2, 2, 3], [1, 1, 2, 2, 3]],
            '2' => [[0, 0, 1, 1, 1, 1, 2, 3, 3], [0, 0, 1, 1, 2, 3, 3]],
        ];
    }

    public function test_majority_element(): void
    {
        $nums = [3, 2, 3];

        $m = 0;
        $r = 0;
        foreach ($nums as $num) {
            if ($m === 0) {
                $r = $num;
            }

            $m += ($num == $r) ? 1 : -1;
        }

        dd($m, $r);
    }

    public function test_rotate_array(): void
    {
        $nums = [1,2,3,4,5,6,7];
        $exp = [5,6,7,1,2,3,4];

        // 1  2  3  4  5  6  7
        // 7  1  3  4  5  6  7 i = 0
        // 7  1  2  4  5  6  7 i = 1
        // 7  1  2  3  5  6  7 i = 2
        // 7  1  2  3  4  6  7 i = 3
        // 7  1  2  3  4  5  7 i = 4
        // 7  1  2  3  4  5  6 i = 5

        $k = 3;

        $length = count($nums);
        $prev = 0;
        for ($i = 0; $i < $length; $i++) {
//            $delta = $length - $k + $i;
//            $delta = $delta === $length ? $length - $delta : $delta;

            $temp = $nums[$i];
            $next = $nums[$length - $i];
            $nums[$i + 1] = $temp;
            $nums[$i] = $next;

        }

        dd($nums);
    }
}
