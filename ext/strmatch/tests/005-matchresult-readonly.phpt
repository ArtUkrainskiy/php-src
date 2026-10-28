--TEST--
Str\MatchResult: properties are readonly
--EXTENSIONS--
strmatch
--FILE--
<?php
$r = (new Str\Matcher(['ab', 'bc']))->matchAll('zabc')[0];
printf("%d %d %s\n", $r->offset, $r->length, $r->keyword);
try { $r->keyword = 'x'; } catch (\Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
1 2 ab
Cannot modify readonly property Str\MatchResult::$keyword
