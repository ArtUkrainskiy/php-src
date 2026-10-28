--TEST--
Str\Matcher::matchAll against an independent strpos occurrence oracle (seeded random)
--EXTENSIONS--
strmatch
--FILE--
<?php
// independent oracle: strpos-walk each keyword into (offset, length, keyword), then sort
// by offset then length -- must equal matchAll (offsets, lengths, overlaps, order, count)
mt_srand(424242);
function rw(): string { $n = mt_rand(1, 5); $s = ''; for ($i = 0; $i < $n; $i++) $s .= chr(mt_rand(97, 100)); return $s; }

$fails = 0;
for ($t = 0; $t < 150; $t++) {
    $keys = [];
    $nk = mt_rand(1, 10);
    for ($i = 0; $i < $nk; $i++) $keys[rw()] = true;
    $keys = array_keys($keys);
    $sub = '';
    $sl = mt_rand(0, 30);
    for ($i = 0; $i < $sl; $i++) $sub .= chr(mt_rand(97, 100));

    $oracle = [];
    foreach ($keys as $w) { $o = 0; while (($p = strpos($sub, $w, $o)) !== false) { $oracle[] = [$p, strlen($w), $w]; $o = $p + 1; } }
    usort($oracle, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

    $got = [];
    foreach ((new Str\Matcher($keys))->matchAll($sub) as $r) $got[] = [$r->offset, $r->length, $r->keyword];

    if ($oracle !== $got) $fails++;
}
echo $fails === 0 ? "150 matchAll cases OK\n" : "FAILED $fails\n";
?>
--EXPECT--
150 matchAll cases OK
