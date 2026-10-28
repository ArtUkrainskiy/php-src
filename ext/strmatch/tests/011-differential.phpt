--TEST--
Str\Replacer vs strtr and Str\Matcher vs str_contains, seeded random differential
--EXTENSIONS--
strmatch
--FILE--
<?php
// short keywords over a 3-letter alphabet produce many overlaps and shared prefixes
mt_srand(20261028);
function rw(): string { $n = mt_rand(1, 6); $s = ''; for ($i = 0; $i < $n; $i++) $s .= chr(mt_rand(97, 99)); return $s; }

$fails = 0;
for ($t = 0; $t < 200; $t++) {
    $map = [];
    $nk = mt_rand(1, 12);
    for ($i = 0; $i < $nk; $i++) $map[rw()] = strtoupper(rw());
    $subject = '';
    $sl = mt_rand(0, 40);
    for ($i = 0; $i < $sl; $i++) $subject .= chr(mt_rand(97, 99));

    if ((new Str\Replacer($map))->replace($subject) !== strtr($subject, $map)) $fails++;

    $keys = array_keys($map);
    $oracle = [];
    foreach ($keys as $w) if (str_contains($subject, $w)) $oracle[] = $w;
    sort($oracle);
    $got = (new Str\Matcher($keys))->getMatchedKeywords($subject);
    sort($got);
    if ($oracle !== $got) $fails++;
}
echo $fails === 0 ? "200 random cases OK\n" : "FAILED $fails\n";
?>
--EXPECT--
200 random cases OK
