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
        $nums = [1, 2, 3, 4, 5, 6, 7];
        $exp = [5, 6, 7, 1, 2, 3, 4];

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

    public function test_jump_game_2(): void
    {
        $nums = [2, 3, 0, 1, 4];
        //       0  1  2  3  4  5  6  7  8  9  10 11


        $iterates = 0;

        $jumps = 0;
        $currentEnd = 0;
        $farthest = 0;
        for ($i = 0; $i < count($nums) - 1; $i++) {
            $farthest = max($farthest, $i + $nums[$i]);
            if ($i === $currentEnd) {
                $jumps++;
                $currentEnd = $farthest;
            }
        }

        dd("Steps: $jumps");
    }

    public function test_catalog(): void
    {
        $s = "<prod><name>drill</name><prx>99</prx><qty>5</qty></prod>

<prod><name>hammer</name><prx>10</prx><qty>50</qty></prod>

<prod><name>screwdriver</name><prx>5</prx><qty>51</qty></prod>

<prod><name>table saw</name><prx>1099.99</prx><qty>5</qty></prod>

<prod><name>saw</name><prx>9</prx><qty>10</qty></prod>

<prod><name>chair</name><prx>100</prx><qty>20</qty></prod>

<prod><name>fan</name><prx>50</prx><qty>8</qty></prod>

<prod><name>wire</name><prx>10.8</prx><qty>15</qty></prod>

<prod><name>battery</name><prx>150</prx><qty>12</qty></prod>

<prod><name>pallet</name><prx>10</prx><qty>50</qty></prod>

<prod><name>wheel</name><prx>8.80</prx><qty>32</qty></prod>

<prod><name>extractor</name><prx>105</prx><qty>17</qty></prod>

<prod><name>bumper</name><prx>150</prx><qty>3</qty></prod>

<prod><name>ladder</name><prx>112</prx><qty>12</qty></prod>

<prod><name>hoist</name><prx>13.80</prx><qty>32</qty></prod>

<prod><name>platform</name><prx>65</prx><qty>21</qty></prod>

<prod><name>car wheel</name><prx>505</prx><qty>7</qty></prod>

<prod><name>bicycle wheel</name><prx>150</prx><qty>11</qty></prod>

<prod><name>big hammer</name><prx>18</prx><qty>12</qty></prod>

<prod><name>saw for metal</name><prx>13.80</prx><qty>32</qty></prod>

<prod><name>wood pallet</name><prx>65</prx><qty>21</qty></prod>

<prod><name>circular fan</name><prx>80</prx><qty>8</qty></prod>

<prod><name>exhaust fan</name><prx>62</prx><qty>8</qty></prod>

<prod><name>window fan</name><prx>62</prx><qty>8</qty></prod>

";

        $search = 'ladder';

        $result = [];
        $data = simplexml_load_string('<main>'.$s.'</main>');
        foreach ($data->prod as $product) {
            if (strpos($product->name, $search) !== false) {
                $result[] = "$product->name > prx: $$product->prx qty: $product->qty";
            }
        }

        $this->assertEquals("ladder > prx: $112 qty: 12", implode("\n", $result));
    }
}
