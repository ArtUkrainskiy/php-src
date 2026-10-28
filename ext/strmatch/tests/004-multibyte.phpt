--TEST--
Str\Matcher / Str\Replacer: UTF-8 keywords match on character boundaries, byte offsets
--EXTENSIONS--
strmatch
--FILE--
<?php
$m = new Str\Matcher(['日本', 'мир', 'niño']);
var_dump($m->countMatches('日本と日本、мир niño'));
foreach ($m->matchAll('x日本y') as $r) {
    printf("%d:%d:%s\n", $r->offset, $r->length, $r->keyword);
}
$r = new Str\Replacer(['мир' => 'peace']);
var_dump($r->replace('мир миру мир'));
?>
--EXPECT--
int(4)
1:6:日本
string(19) "peace peaceу peace"
