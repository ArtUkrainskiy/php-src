--TEST--
strpos: sparse and dense prefixes, binary strings, and boundary matches
--FILE--
<?php
// General regression cases retained across search implementations. Compare each
// result against fixed-position comparisons, independently of the search engine.

function naive(string $h, string $n, int $off = 0): int|false {
    $hl = strlen($h); $nl = strlen($n);
    if ($nl === 0) { return $off <= $hl ? $off : false; }
    for ($i = $off; $i + $nl <= $hl; $i++) {
        if (substr_compare($h, $n, $i, $nl) === 0) { return $i; }
    }
    return false;
}

$cases = [];

$pad = str_repeat('a', 2000);

// A distinguishing byte occurs only in the match.
$cases['sparse present'] = [$pad . 'zqw' . $pad, 'zqw'];
// The distinguishing byte is absent.
$cases['sparse absent'] = [$pad . $pad, 'zqw'];
// Many matching first bytes precede a complete match.
$cases['dense then match'] = [str_repeat('z', 100) . 'zqw' . $pad, 'zqw'];
// Sparse matching first bytes become dense; the complete needle is absent.
$cases['sparse then dense'] = [str_repeat(str_repeat('a', 199) . 'z', 16) . str_repeat('z', 4000), 'zqw'];
// A match follows the sparse prefix.
$cases['match 2nd window'] = [str_repeat(str_repeat('a', 199) . 'z', 16) . 'zqw' . $pad, 'zqw'];
// Match exactly at the last valid start position.
$cases['match at end'] = [$pad . 'zend', 'zend'];
// The first byte occurs once, next to the end.
$cases['anchor at end'] = [$pad . 'qz', 'qz'];
// Common letters and a long repeating prefix.
$cases['allcommon present'] = [$pad . 'aaab' . $pad, 'aaab'];
// Needles longer than 256 bytes, present and absent.
$long = str_repeat('a', 300) . 'z';
$cases['long present'] = [$pad . $long . $pad, $long];
$cases['long absent'] = [$pad . $pad, str_repeat('a', 300) . 'q'];
// A haystack barely longer than the needle.
$cases['few positions'] = [str_repeat('a', 260) . 'z', str_repeat('a', 259) . 'z'];
// A larger haystack with few valid start positions.
$cases['few long present'] = [str_repeat('a', 1900) . 'z' . str_repeat('a', 147), str_repeat('a', 1900) . 'z'];
$cases['few long absent'] = [str_repeat('a', 2048), str_repeat('a', 1900) . 'q'];
// Overlapping matches.
$cases['overlap'] = [$pad . 'zzzz' . $pad, 'zzz'];
// Binary needle including a high byte and NUL.
$cases['binary'] = [$pad . "\xffp\x00q" . $pad, "\xffp\x00q"];
// Adjacent haystack lengths with an absent short needle.
$cases['span- (257)'] = [str_repeat('x', 257), 'ax'];
$cases['span+ (258)'] = [str_repeat('x', 258), 'ax'];
// Adjacent haystack lengths with an early six-byte match.
$cases['early- (261)'] = ['aaaaax' . str_repeat('a', 255), 'aaaaax'];
$cases['early+ (262)'] = ['aaaaax' . str_repeat('a', 256), 'aaaaax'];

$fail = 0;
foreach ($cases as $label => [$h, $n]) {
    $got = strpos($h, $n);
    $want = naive($h, $n);
    $ok = $got === $want;
    if (!$ok) { $fail++; }
    printf("%-20s %s\n", $label, $ok ? 'OK' : "DIFF got=" . var_export($got, true) . " want=" . var_export($want, true));
}
echo $fail ? "FAILED\n" : "ALL OK\n";
?>
--EXPECT--
sparse present       OK
sparse absent        OK
dense then match     OK
sparse then dense    OK
match 2nd window     OK
match at end         OK
anchor at end        OK
allcommon present    OK
long present         OK
long absent          OK
few positions        OK
few long present     OK
few long absent      OK
overlap              OK
binary               OK
span- (257)          OK
span+ (258)          OK
early- (261)         OK
early+ (262)         OK
ALL OK
