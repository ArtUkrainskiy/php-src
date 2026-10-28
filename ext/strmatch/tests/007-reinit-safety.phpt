--TEST--
Str\Matcher: a failed re-construct keeps the previous automaton; parent ctor is rejected on Replacer
--EXTENSIONS--
strmatch
--FILE--
<?php
// a throwing __construct must not destroy the working automaton or leave a half-built one
$m = new Str\Matcher(['foo']);
try { $m->__construct(['bar', 123]); } catch (\TypeError $e) { echo "TypeError\n"; }
var_dump($m->matches('foo'), $m->matches('bar'));

// the parent Str\Matcher constructor cannot be run on a Str\Replacer
$r = new Str\Replacer(['a' => 'b']);
try { (new ReflectionMethod(Str\Matcher::class, '__construct'))->invoke($r, ['a']); }
catch (\Error $e) { echo $e->getMessage(), "\n"; }
echo $r->replace('a'), "\n";
?>
--EXPECT--
TypeError
bool(true)
bool(false)
Str\Matcher::__construct() cannot be called on a Str\Replacer
b
