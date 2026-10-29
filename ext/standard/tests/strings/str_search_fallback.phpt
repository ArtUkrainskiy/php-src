--TEST--
strpos: substring search hitting the rare-byte / density-fallback branches
--FILE--
<?php
// Deterministic inputs built to reach each internal branch of the search (rare anchor
// sparse/dense, the per-window density re-check, the long-needle and few-positions
// fallbacks, and matches right at the window or haystack boundary). Each result is checked
// against a naive reference, so the test passes only if behaviour matches the old search.

function naive(string $h, string $n, int $off = 0): int|false {
    $hl = strlen($h); $nl = strlen($n);
    if ($nl === 0) { return $off <= $hl ? $off : false; }
    for ($i = $off; $i + $nl <= $hl; $i++) {
        if (substr_compare($h, $n, $i, $nl) === 0) { return $i; }
    }
    return false;
}

$cases = [];

// 'z' is a rare anchor byte; 'a'/' ' are common. Haystacks are > needle + 256 so the
// rare-byte path is taken.
$pad = str_repeat('a', 2000);

// 1. Rare anchor sparse, match present (win path, no density bail).
$cases['sparse present'] = [$pad . 'zqw' . $pad, 'zqw'];
// 2. Rare anchor sparse, absent.
$cases['sparse absent'] = [$pad . $pad, 'zqw'];
// 3. Anchor dense from the start -> density bail -> found by fallback.
$cases['dense then match'] = [str_repeat('z', 100) . 'zqw' . $pad, 'zqw'];
// 4. Anchor sparse for a full window (16 'z' at 200-byte gaps, above the density gap so the
//    first window is kept), then dense; absent. Reaches the per-window density re-check.
$cases['sparse then dense'] = [str_repeat(str_repeat('a', 199) . 'z', 16) . str_repeat('z', 4000), 'zqw'];
// 4b. Match arriving in the second window, just after a sparse first window.
$cases['match 2nd window'] = [str_repeat(str_repeat('a', 199) . 'z', 16) . 'zqw' . $pad, 'zqw'];
// 5. Match exactly at the last valid start position.
$cases['match at end'] = [$pad . 'zend', 'zend'];
// 6. Anchor appears once, right at the end.
$cases['anchor at end'] = [$pad . 'qz', 'qz'];
// 7. All-common needle (no rare byte) -> best>=common -> original search, present.
$cases['allcommon present'] = [$pad . 'aaab' . $pad, 'aaab'];
// 8. Needle longer than 256 (long-needle fallback), present and absent.
$long = str_repeat('a', 300) . 'z';
$cases['long present'] = [$pad . $long . $pad, $long];
$cases['long absent'] = [$pad . $pad, str_repeat('a', 300) . 'q'];
// 9. Few start positions (haystack barely longer than needle) -> few-positions fallback.
$cases['few positions'] = [str_repeat('a', 260) . 'z', str_repeat('a', 259) . 'z'];
// 9b. Long needle (>=9), large haystack (>=1024), few positions -> original Sunday path kept.
$cases['few long present'] = [str_repeat('a', 1900) . 'z' . str_repeat('a', 147), str_repeat('a', 1900) . 'z'];
$cases['few long absent'] = [str_repeat('a', 2048), str_repeat('a', 1900) . 'q'];
// 10. Overlap with a rare anchor.
$cases['overlap'] = [$pad . 'zzzz' . $pad, 'zzz'];
// 11. Binary needle with a rare high byte.
$cases['binary'] = [$pad . "\xffp\x00q" . $pad, "\xffp\x00q"];
// 12. Both sides of the MIN_SPAN boundary: 'ax' in all-'x' (anchor 'x' is dense), absent.
$cases['span- (257)'] = [str_repeat('x', 257), 'ax'];
$cases['span+ (258)'] = [str_repeat('x', 258), 'ax'];
// 13. Both sides of the boundary with an early match, 6-byte needle.
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
