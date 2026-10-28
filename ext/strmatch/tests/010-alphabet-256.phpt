--TEST--
Str\Matcher: full 256-byte alphabet (every byte value in a keyword)
--EXTENSIONS--
strmatch
--FILE--
<?php
$kw = [];
for ($i = 0; $i < 256; $i++) $kw[] = chr($i) . chr(255 - $i);
$m = new Str\Matcher($kw);

$subject = '';
for ($i = 0; $i < 512; $i++) $subject .= chr(($i * 37 + 11) % 256);
$subject .= $kw[0] . $kw[128] . $kw[255] . $kw[7];

$oracle = [];
foreach ($kw as $w) if (str_contains($subject, $w)) $oracle[] = $w;
sort($oracle);
$got = $m->getMatchedKeywords($subject);
sort($got);
echo $oracle === $got ? "match set OK\n" : "match set DIFF\n";

$cnt = 0;
foreach ($kw as $w) { $o = 0; while (($p = strpos($subject, $w, $o)) !== false) { $cnt++; $o = $p + 1; } }
echo $m->countMatches($subject) === $cnt ? "count OK\n" : "count DIFF\n";
?>
--EXPECT--
match set OK
count OK
