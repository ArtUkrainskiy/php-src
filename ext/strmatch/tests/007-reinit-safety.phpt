--TEST--
Str\Matcher: a failed re-construct keeps the previous automaton; Replacer rebuilt via parent ctor is rejected
--EXTENSIONS--
strmatch
--FILE--
<?php
// a throwing __construct must not destroy the working automaton or leave a half-built one
$m = new Str\Matcher(['foo']);
try { $m->__construct(['bar', 123]); } catch (\TypeError $e) { echo "TypeError\n"; }
var_dump($m->matches('foo'), $m->matches('bar'));

// invoking the parent Str\Matcher constructor on a Replacer leaves no replacements;
// replace() must reject it, not crash
$r = new Str\Replacer(['a' => 'b']);
try {
    (new ReflectionMethod(Str\Matcher::class, '__construct'))->invoke($r, ['a']);
    $r->replace('a');
} catch (\Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
TypeError
bool(true)
bool(false)
Str\Replacer was not initialized with replacements
