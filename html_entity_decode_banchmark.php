<?php
ini_set('memory_limit', -1);

class Benchmark
{
    // This static property tracks if the header has been printed already
    private static bool $headerPrinted = false;

    /**
     * Runs the benchmark for two given functions, measuring in nanoseconds.
     *
     * @param callable $func1 The first function to be benchmarked
     * @param callable $func2 The second function to be benchmarked
     * @param array $param A string parameter passed to both functions
     * @param int $iterations Number of iterations for each function
     */
    public function runBenchmark(string $title, callable $func1, callable $func2, array $param, int $iterations = 1000): void
    {
        // If header wasn't printed yet, print it once
        if (!self::$headerPrinted) {
            $this->printHeader();
            self::$headerPrinted = true;
        }
        assert($func1($param) === $func2($param));

        // Arrays to store execution times (in nanoseconds)
        $timesFunc1 = [];
        $timesFunc2 = [];

        // Benchmark for func1
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $func1($param);
            $end = hrtime(true);

            $timesFunc1[] = $end - $start;
        }

        // Benchmark for func2
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $func2($param);
            $end = hrtime(true);

            $timesFunc2[] = $end - $start;
        }

        // Calculate statistics
        $statsFunc1 = $this->calculateStats($timesFunc1);
        $statsFunc2 = $this->calculateStats($timesFunc2);

        // Calculate percentage difference relative to func2's average
        $diffPercent = 0.0;
        if ($statsFunc2['min'] > 0) {
            $diffPercent = ($statsFunc1['min'] / $statsFunc2['min'] - 1) * 100;
        }

        // Print a single row in the table
        $this->printResultLine($title, $statsFunc1, $statsFunc2, $diffPercent);
    }

    /**
     * Calculates average, min, and max for an array of times (nanoseconds).
     *
     * @param array $times Array of times for one of the functions
     * @return array ['average' => int, 'min' => int, 'max' => int]
     */
    private function calculateStats(array $times): array
    {
        $count = count($times);
        if ($count === 0) {
            return [
                'average' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        $sum = array_sum($times);
        $average = (int)($sum / $count);
        $min = min($times);
        $max = max($times);

        return [
            'average' => $average,
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * Prints the table header (aligned columns).
     */
    private function printHeader(): void
    {
        // Print top separator
        $this->printSeparator();

        // Print the header row
        // 7 columns: Func1 avg, Func1 min, Func1 max, Func2 avg, Func2 min, Func2 max, diff (%)
        printf(
            "| %25s | %15s | %15s | %10s |\n",
            "Test",
            "old avg(ns)",
            "new avg(ns)",
            "diff(%)"
        );

        // Print separator under header
        $this->printSeparator();
    }

    /**
     * Prints a single result row in the table.
     *
     * @param array $statsFunc1 Stats array for function 1
     * @param array $statsFunc2 Stats array for function 2
     * @param float $diffPercent Percentage difference (relative to func2 average)
     */
    private function printResultLine(string $title, array $statsFunc1, array $statsFunc2, float $diffPercent): void
    {
        printf(
            "| %25s | %15d | %15d | %9.2f%% |\n",
            $title,
            $statsFunc1['average'],
            $statsFunc2['average'],
            $diffPercent
        );
        $this->printSeparator();
    }

    private function printSeparator(): void
    {
        echo str_repeat("-", 78) . "\n";
    }
}

$benchmark = new Benchmark();

$func1 = static function ($param) {
    return html_entity_decode_old(...$param);
};

$func2 = static function ($param) {
    return html_entity_decode(...$param);
};

$data["4k &"] = str_repeat("&", 4000);
$data["only entities"] = str_repeat("&amp;&gt;&lt;&frasl;", 200);
$data["400 valid entities"] = str_repeat("&amp;qweasdfabcd&lt;", 200);
$data["200 valid entities"] = str_repeat("qwertyuiop&amp;asdfg", 200);
$data["200 invalid entity"] = str_repeat("qwertyuiop&xyz;asdfg", 200);
$data["200 ampersand"] = str_repeat("qwertyuiop&ampasdfgh", 200);
$data["100 valid entities"] = str_repeat("qwertyuiop&amp;asdfgqwertyuiopqwertyuiop", 100);
$data["50 valid entities"] = str_repeat("qwertyuiop&amp;asdfgqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop", 50);
$data["String ends with &"] = str_repeat("abcdefampabcdefghjkl", 200).'&';

foreach ($data as $name => $value) {
    $benchmark->runBenchmark($name, $func1, $func2, [$value], 100000);
}

