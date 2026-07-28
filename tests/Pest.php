<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Architecture');

function assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes(string $html): void
{
    preg_match_all(
        '/\s[A-Za-z_:@][-A-Za-z0-9_:.@]*=([\'"])(?:(?!\1).)*(?:@[A-Za-z_][A-Za-z0-9_]*(?:\s*\(|\b)|\{\{|\{!!)(?:(?!\1).)*\1/s',
        $html,
        $matches,
    );

    expect($matches[0] ?? [])->toBe([]);
}
