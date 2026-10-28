--TEST--
Str\Matcher: empty keyword, non-string, clone and serialize are rejected
--EXTENSIONS--
strmatch
--FILE--
<?php
try { new Str\Matcher(['ok', '']); } catch (\ValueError $e) { echo $e->getMessage(), "\n"; }
try { new Str\Matcher(['ok', 123]); } catch (\TypeError $e) { echo $e->getMessage(), "\n"; }
$m = new Str\Matcher(['x']);
try { clone $m; } catch (\Error $e) { echo $e->getMessage(), "\n"; }
try { serialize($m); } catch (\Exception $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Str\Matcher::__construct(): Argument #1 ($keywords) must not contain an empty keyword
Str\Matcher::__construct(): Argument #1 ($keywords) must be an array of strings
Trying to clone an uncloneable object of class Str\Matcher
Serialization of 'Str\Matcher' is not allowed
