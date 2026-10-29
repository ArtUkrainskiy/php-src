--TEST--
strpos/str_contains/strstr/substr_count: differential check over randomized inputs
--FILE--
<?php
// Compares the substring-search functions against a naive reference over seeded random
// inputs. The reference uses substr_compare at fixed offsets, so it does not share the
// search engine under test. Inputs are shaped to exercise needles with and without a rare
// byte, all-common needles, lengths straddling the internal thresholds, and present/absent
// matches in haystacks of several sizes.

function naive_pos(string $h, string $n, int $off = 0): int|false {
    $hl = strlen($h); $nl = strlen($n);
    if ($nl === 0) { return $off <= $hl ? $off : false; }
    for ($i = $off; $i + $nl <= $hl; $i++) {
        if (substr_compare($h, $n, $i, $nl) === 0) { return $i; }
    }
    return false;
}
function naive_count(string $h, string $n): int {
    $hl = strlen($h); $nl = strlen($n); if ($nl === 0) { return 0; }
    $c = 0; $i = 0;
    while ($i + $nl <= $hl) {
        if (substr_compare($h, $n, $i, $nl) === 0) { $c++; $i += $nl; } else { $i++; }
    }
    return $c;
}

$alphabets = [
    ['a', 'b', 'c'],                                   // tiny: overlaps, all-common
    str_split('wordx 0123'),                           // common letters + a rare 'x'
    str_split('the quick brown fox'),                  // words
    array_map('chr', [0, 1, 2, 9, 10, 32, 65, 97, 122, 127, 200, 255]), // binary + NUL
];

mt_srand(20261029);
$fail = 0; $checked = 0;

for ($t = 0; $t < 6000; $t++) {
    $alpha = $alphabets[mt_rand(0, count($alphabets) - 1)];
    $n = count($alpha);

    // Haystack size straddles the rare-path routing (64) and the long-needle cap (256).
    $hl = [0, 1, 20, 63, 64, 65, 200, 300, 2000][mt_rand(0, 8)];
    $h = '';
    for ($i = 0; $i < $hl; $i++) { $h .= $alpha[mt_rand(0, $n - 1)]; }

    // Half the time a real substring (present), half an independent needle (usually absent).
    if ($hl > 0 && mt_rand(0, 1)) {
        $start = mt_rand(0, $hl - 1);
        $nl = mt_rand(1, min($hl - $start, 300));
        $needle = substr($h, $start, $nl);
    } else {
        $nl = mt_rand(0, 300);
        $needle = '';
        for ($i = 0; $i < $nl; $i++) { $needle .= $alpha[mt_rand(0, $n - 1)]; }
    }

    $off = $hl > 0 ? mt_rand(0, $hl) : 0;

    if (strpos($h, $needle, $off) !== naive_pos($h, $needle, $off)) { $fail++; }
    if (str_contains($h, $needle) !== (naive_pos($h, $needle, 0) !== false)) { $fail++; }
    $want_strstr = ($p = naive_pos($h, $needle, 0)) === false ? false : substr($h, $p);
    if (strstr($h, $needle) !== $want_strstr) { $fail++; }
    if ($needle !== '' && substr_count($h, $needle) !== naive_count($h, $needle)) { $fail++; }

    $checked++;
}

echo "checked $checked\n";
echo $fail ? "FAILED ($fail)\n" : "ALL OK\n";
?>
--EXPECT--
checked 6000
ALL OK
