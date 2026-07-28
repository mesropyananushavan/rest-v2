<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
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

/**
 * Assert that every rendered Livewire binding points at a real component target.
 *
 * Covered bindings:
 * - Action directives (`wire:click`, `wire:submit`, `wire:change`,
 *   `wire:keydown`, and their modifiers) must be clean public method calls.
 * - The Livewire `$set('property', value)` action is allowed only when the
 *   assigned root property is public on the component.
 * - Model directives (`wire:model` and modifiers) resolve only the root public
 *   property; dynamic segments such as `moveTargetSubtableIds.123` are array
 *   keys controlled by runtime state, so this contract validates
 *   `moveTargetSubtableIds` and leaves the segment value to feature tests.
 *
 * Ignored directives are Livewire-owned runtime/state attributes rather than
 * component binding targets: `wire:id`, `wire:name`, `wire:snapshot`,
 * `wire:effects`, `wire:key`, `wire:navigate`, `wire:loading*`, `wire:transition*`,
 * `wire:ignore*`, and `wire:poll*`.
 *
 * @param  class-string  $componentClass
 */
function assertRenderedLivewireBindingsResolve(string $html, string $componentClass): void
{
    $component = new ReflectionClass($componentClass);
    $failures = [];

    foreach (renderedLivewireBindingAttributes($html) as $attribute) {
        $directive = renderedLivewireDirectiveName($attribute['name']);
        $value = trim($attribute['value']);

        if ($value === '') {
            $failures[] = sprintf('%s has empty %s binding.', $componentClass, $attribute['name']);

            continue;
        }

        if (renderedLivewireExpressionHasUncompiledBladeMarker($value)) {
            $failures[] = sprintf(
                '%s %s="%s" contains an uncompiled Blade marker.',
                $componentClass,
                $attribute['name'],
                $value,
            );

            continue;
        }

        if ($directive === 'model') {
            $property = renderedLivewirePropertyRoot($value);

            if (! renderedLivewirePublicPropertyExists($component, $property)) {
                $failures[] = sprintf(
                    '%s %s="%s" references missing public property [%s].',
                    $componentClass,
                    $attribute['name'],
                    $value,
                    $property,
                );
            }

            continue;
        }

        if (str_starts_with($value, '$set(')) {
            $property = renderedLivewireSetProperty($value);

            if ($property === null || ! renderedLivewirePublicPropertyExists($component, $property)) {
                $failures[] = sprintf(
                    '%s %s="%s" references missing public $set property [%s].',
                    $componentClass,
                    $attribute['name'],
                    $value,
                    $property ?? '<unparseable>',
                );
            }

            continue;
        }

        $method = renderedLivewireActionMethod($value);

        if ($method === null) {
            $failures[] = sprintf(
                '%s %s="%s" is not a clean Livewire method call.',
                $componentClass,
                $attribute['name'],
                $value,
            );

            continue;
        }

        if (! renderedLivewirePublicMethodExists($component, $method)) {
            $failures[] = sprintf(
                '%s %s="%s" references missing public method [%s].',
                $componentClass,
                $attribute['name'],
                $value,
                $method,
            );
        }
    }

    Assert::assertSame(
        [],
        $failures,
        "Rendered Livewire bindings must resolve for {$componentClass}:\n".implode("\n", $failures),
    );
}

/**
 * @return list<array{name: string, value: string}>
 */
function renderedLivewireBindingAttributes(string $html): array
{
    $document = new DOMDocument('1.0', 'UTF-8');

    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="UTF-8"><div>'.$html.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $bindings = [];

    foreach ($document->getElementsByTagName('*') as $element) {
        foreach ($element->attributes ?? [] as $attribute) {
            if (! str_starts_with($attribute->name, 'wire:')) {
                continue;
            }

            if (renderedLivewireDirectiveIsIgnored($attribute->name)) {
                continue;
            }

            $bindings[] = [
                'name' => $attribute->name,
                'value' => $attribute->value,
            ];
        }
    }

    return $bindings;
}

function renderedLivewireDirectiveName(string $attributeName): string
{
    $directive = substr($attributeName, strlen('wire:'));

    return explode('.', $directive, 2)[0] ?? $directive;
}

function renderedLivewireDirectiveIsIgnored(string $attributeName): bool
{
    $directive = renderedLivewireDirectiveName($attributeName);

    if (in_array($directive, ['id', 'name', 'snapshot', 'effects', 'key', 'navigate'], true)) {
        return true;
    }

    foreach (['loading', 'transition', 'ignore', 'poll'] as $prefix) {
        if ($directive === $prefix || str_starts_with($directive, "{$prefix}.")) {
            return true;
        }
    }

    return false;
}

function renderedLivewirePropertyRoot(string $modelExpression): string
{
    return explode('.', $modelExpression, 2)[0] ?? $modelExpression;
}

function renderedLivewireSetProperty(string $expression): ?string
{
    if (! preg_match('/^\$set\(\s*([\'"])([A-Za-z_][A-Za-z0-9_]*)(?:\.[^\'"]+)?\1\s*,/s', $expression, $matches)) {
        return null;
    }

    return $matches[2];
}

function renderedLivewireExpressionHasUncompiledBladeMarker(string $expression): bool
{
    return str_contains($expression, '{{')
        || str_contains($expression, '{!!')
        || preg_match('/@[A-Za-z_][A-Za-z0-9_]*\s*\(/', $expression) === 1;
}

function renderedLivewireActionMethod(string $expression): ?string
{
    if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(?:\(.*\))?$/s', $expression, $matches)) {
        return null;
    }

    return $matches[1];
}

/**
 * @param  ReflectionClass<object>  $component
 */
function renderedLivewirePublicPropertyExists(ReflectionClass $component, string $property): bool
{
    return $component->hasProperty($property) && $component->getProperty($property)->isPublic();
}

/**
 * @param  ReflectionClass<object>  $component
 */
function renderedLivewirePublicMethodExists(ReflectionClass $component, string $method): bool
{
    return $component->hasMethod($method) && $component->getMethod($method)->isPublic();
}
