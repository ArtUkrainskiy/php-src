<?php

/** @generate-class-entries */

namespace Str;

class Matcher
{
    public function __construct(array $keywords) {}

    public function matches(string $subject): bool {}

    public function countMatches(string $subject): int {}

    /** @return list<string> */
    public function getMatchedKeywords(string $subject): array {}

    /** @return list<\Str\MatchResult> */
    public function matchAll(string $subject): array {}

    /** @return list<string> */
    public function getKeywords(): array {}
}

final class MatchResult
{
    public readonly int $offset;
    public readonly int $length;
    public readonly string $keyword;
}

final class Replacer extends Matcher
{
    public function __construct(array $replacements) {}

    public function replace(string $subject, ?int &$count = null): string {}
}
