--TEST--
Str\Matcher: binary-safe keywords (embedded NUL and high bytes)
--EXTENSIONS--
strmatch
--FILE--
<?php
$m = new Str\Matcher(["a\0b", "\xff\xfe"]);
var_dump($m->countMatches("x\xff\xfey a\0b z a\0b"));
var_dump($m->matches("a\0b"));
?>
--EXPECT--
int(3)
bool(true)
