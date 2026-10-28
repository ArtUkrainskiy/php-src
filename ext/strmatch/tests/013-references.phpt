--TEST--
Str\Matcher / Str\Replacer: references to string values are accepted
--EXTENSIONS--
strmatch
--FILE--
<?php
$k = 'hello';
var_dump((new Str\Matcher([&$k]))->matches('say hello'));
$v = 'world';
echo (new Str\Replacer(['hi' => &$v]))->replace('hi there'), "\n";
?>
--EXPECT--
bool(true)
world there
