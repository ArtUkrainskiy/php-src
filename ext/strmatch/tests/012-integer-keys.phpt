--TEST--
Str\Replacer: integer keys stringify signed, like strtr
--EXTENSIONS--
strmatch
--FILE--
<?php
$map = [-1 => 'X', 123 => 'Y', 'ab' => 'Z'];
var_dump((new Str\Replacer($map))->replace('q-1 123 ab') === strtr('q-1 123 ab', $map));
?>
--EXPECT--
bool(true)
