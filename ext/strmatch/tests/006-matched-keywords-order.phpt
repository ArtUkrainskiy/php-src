--TEST--
Str\Matcher::getMatchedKeywords returns first-occurrence (start) order
--EXTENSIONS--
strmatch
--FILE--
<?php
// 'abcd' starts at 0, 'bc' at 1 -> abcd first even though it ends later
print_r((new Str\Matcher(['abcd', 'bc']))->getMatchedKeywords('abcd'));
// tie on start offset (she@1, he@2, hers@2) -> shorter first among equal starts
print_r((new Str\Matcher(['he', 'she', 'hers']))->getMatchedKeywords('ushers'));
?>
--EXPECT--
Array
(
    [0] => abcd
    [1] => bc
)
Array
(
    [0] => she
    [1] => he
    [2] => hers
)
