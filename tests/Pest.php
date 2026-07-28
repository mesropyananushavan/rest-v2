<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Architecture');

function assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes(string $html): void
{
    preg_match_all(
        '/\s[A-Za-z_:@][-A-Za-z0-9_:.@]*=([\'"])(?:(?!\1).)*(?:@(js|json|class|style|lang|choice|checked|selected|disabled|readonly|required|error|props|aware|once|push|prepend|section|yield|include|each|extends|vite|livewireScriptConfig)\s*\(|\{\{|\{!!)(?:(?!\1).)*\1/s',
        $html,
        $matches,
    );

    expect($matches[0] ?? [])->toBe([]);
}
