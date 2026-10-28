--TEST--
Str\Matcher: matches, countMatches, getMatchedKeywords, matchAll, getKeywords
--EXTENSIONS--
strmatch
--FILE--
<?php
$m = new Str\Matcher(['he', 'she', 'his', 'hers']);
var_dump($m->matches('ushers'), $m->matches('nothing'));
var_dump($m->countMatches('ushers'));
print_r($m->getMatchedKeywords('ushers'));
foreach ($m->matchAll('ushers') as $r) {
    printf("%d:%d:%s\n", $r->offset, $r->length, $r->keyword);
}
print_r($m->getKeywords());
?>
--EXPECT--
bool(true)
bool(false)
int(3)
Array
(
    [0] => she
    [1] => he
    [2] => hers
)
1:3:she
2:2:he
2:4:hers
Array
(
    [0] => he
    [1] => she
    [2] => his
    [3] => hers
)
