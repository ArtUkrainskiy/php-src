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

// Short binary inputs and nonzero offsets exercise the inline search.
foreach ([2, 3, 8, 9, 15, 16, 17, 31, 32, 33, 63, 64, 65, 255, 256, 257] as $length) {
    $needle = "\x00" . str_repeat('a', $length - 2) . "\xff";
    for ($tail = 0; $tail < 16; $tail++) {
        $prefix = str_repeat("\x00a", 100) . str_repeat('a', $tail);
        check_search($prefix . $needle, $needle);
        check_search($prefix . str_repeat('a', $length), $needle);
    }
}

// Repeating prefixes with a distinguishing byte, present and absent.
foreach (['ab', 'abc', 'aab', 'abcabd'] as $period) {
    $needle = str_repeat($period, 40) . 'q' . $period;
    check_search(str_repeat($period, 4000), $needle);
    check_search(str_repeat($period, 4000) . $needle, $needle);
}

// For a^(length-1)b in an a-only prefix, Sunday advances by two bytes.
// Sixteen long mismatches hand off at absolute offset 31. Exercise pair
// block boundaries and all fifteen starts in a partial final block.
foreach ([32, 33, 63, 64, 65, 255, 256, 257] as $length) {
    $needle = str_repeat('a', $length - 1) . 'b';
    $size = 8192 + (($length + 13) % 16);
    $positions = [31 + 15, 31 + 16, 31 + 17];
    for ($tail = 0; $tail < 15; $tail++) {
        $positions[] = $size - $length - $tail;
    }
    check_search(str_repeat('a', $size), $needle);
    foreach ($positions as $position) {
        $haystack = str_repeat('a', $size);
        $haystack[$position + $length - 1] = 'b';
        check_search($haystack, $needle);
    }
}

// The same costly prefix reaches the pair filter. Alternating ab then makes
// pairs dense but the aaab gram selective; aaab makes the gram dense too.
$needle = str_repeat('a', 31) . 'b';
$prefix = str_repeat('a', 64) . 'c';
$selective = substr($prefix . str_repeat('ab', 8192), 0, 16384);
$dense = substr($prefix . str_repeat('aaab', 4096), 0, 16384);
$changing = substr($prefix . str_repeat('ab', 4096), 0, 8192)
    . str_repeat('aaab', 14336);
foreach ([$selective, $dense, $changing] as $haystack) {
    check_search($haystack, $needle);
    check_search(substr_replace($haystack, $needle, -strlen($needle)), $needle);
}

// A truly periodic needle reaches Two-Way after both filters become dense.
// A right-half match followed by a left-half mismatch exercises remembered work.
$needle = str_repeat('ab', 16);
$haystack = str_repeat('bb' . str_repeat('ab', 15), 2048);
check_search($haystack, $needle);
check_search(substr_replace($haystack, 'c' . $needle, -33), $needle);

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
checked 703
ALL OK
