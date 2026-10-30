--TEST--
Substring search across adaptive and Two-Way handoffs
--FILE--
<?php
function reference_pos(string $haystack, string $needle, int $offset = 0): int|false {
    $last = strlen($haystack) - strlen($needle);
    for ($i = $offset; $i <= $last; $i++) {
        if (substr_compare($haystack, $needle, $i, strlen($needle)) === 0) {
            return $i;
        }
    }
    return false;
}

$checked = 0;
$failed = 0;
function check_search(string $haystack, string $needle): void {
    global $checked, $failed;
    $expected = reference_pos($haystack, $needle);
    $failed += strpos($haystack, $needle) !== $expected;
    $failed += str_contains($haystack, $needle) !== ($expected !== false);
    $failed += strstr($haystack, $needle) !== ($expected === false ? false : substr($haystack, $expected));
    // Count via fixed-position comparisons, independent of zend_memnstr.
    $count = 0;
    $position = 0;
    while (($match = reference_pos($haystack, $needle, $position)) !== false) {
        $count++;
        $position = $match + strlen($needle);
    }
    $failed += substr_count($haystack, $needle) !== $count;
    $offset = min(65, strlen($haystack));
    $failed += strpos($haystack, $needle, $offset) !== reference_pos($haystack, $needle, $offset);
    $checked++;
}

// Long common prefixes with both endpoints matching force full verification.
// Change the density later in the haystack and put a match after the handoff.
foreach ([8, 16, 32, 64, 256, 512, 1024] as $length) {
    $needle = str_repeat('ab', intdiv($length, 2) - 1) . 'bb';
    $sparse = str_repeat('a' . str_repeat('x', 199), 70);
    $dense = str_repeat('ab', 10000);
    check_search($sparse . $dense, $needle);
    check_search($sparse . $dense . $needle, $needle);
    check_search($dense . $needle . $dense . $needle, $needle);
}

// SIMD block tails, binary anchors, and nonzero offsets. There are enough
// false first-byte hits before the match to reach the adaptive search.
foreach ([2, 3, 8, 9, 15, 16, 17, 31, 32, 33, 63, 64, 65, 255, 256, 257] as $length) {
    $needle = "\x00" . str_repeat('a', $length - 2) . "\xff";
    for ($tail = 0; $tail < 16; $tail++) {
        $prefix = str_repeat("\x00a", 100) . str_repeat('a', $tail);
        check_search($prefix . $needle, $needle);
        check_search($prefix . str_repeat('a', $length), $needle);
    }
}

// Periodic needles require Two-Way's remembered prefix after a period shift.
foreach (['ab', 'abc', 'aab', 'abcabd'] as $period) {
    $needle = str_repeat($period, 40) . 'q' . $period;
    check_search(str_repeat($period, 4000), $needle);
    check_search(str_repeat($period, 4000) . $needle, $needle);
}

// The four-byte filter is selective initially and dense later. A late match
// must survive both the pair-to-gram and gram-to-Two-Way handoffs.
$needle = str_repeat('ACGT', 23) . 'TCGT';
$changing = str_repeat('ACGT', 128) . str_repeat('ACGA', 5000) . str_repeat('ACGT', 5000);
check_search($changing, $needle);
check_search($changing . $needle, $needle);
echo "checked $checked\n";
echo $failed ? "FAILED ($failed)\n" : "ALL OK\n";
?>
--EXPECT--
checked 543
ALL OK
