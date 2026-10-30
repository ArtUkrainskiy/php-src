--TEST--
Substring search across short-input and long-needle routing boundaries
--FILE--
<?php
function reference_position(string $haystack, string $needle): int|false {
    $last = strlen($haystack) - strlen($needle);
    for ($i = 0; $i <= $last; $i++) {
        if (substr_compare($haystack, $needle, $i, strlen($needle)) === 0) {
            return $i;
        }
    }
    return false;
}
$checked = 0;
$failures = 0;
foreach ([8, 9, 31, 32, 33, 64, 256] as $length) {
    $needle = substr(str_repeat('ab', $length), 0, $length - 2) . 'bb';
    foreach ([255, 256, 1023, 1024, 2047, 2048, 4095, 4096, 8191, 8192, 8193, 16384] as $size) {
        $base = substr(str_repeat('ab', $size), 0, $size);
        foreach ([$base, $needle . $base, $base . $needle] as $haystack) {
            $expected = reference_position($haystack, $needle);
            $failures += strpos($haystack, $needle) !== $expected;
            $failures += str_contains($haystack, $needle) !== ($expected !== false);
            $checked++;
        }
    }
}
// Everyday UTF-8 inputs that caused premature preprocessing in the broad prototype.
foreach (['посылка пришла вовремя, упаковка целая. ',
          'पार्सल समय पर आया और पैकिंग सही थी। '] as $message) {
    foreach ([1, 4, 32, 256] as $copies) {
        $haystack = str_repeat($message, $copies);
        foreach (['спам', 'накрутка подписчиков', 'स्पैम', 'गारंटीकृत लाभ'] as $needle) {
            $failures += strpos($haystack, $needle) !== reference_position($haystack, $needle);
            $checked++;
        }
    }
}
echo "checked $checked\n";
echo $failures ? "FAILED ($failures)\n" : "ALL OK\n";
?>
--EXPECT--
checked 284
ALL OK
