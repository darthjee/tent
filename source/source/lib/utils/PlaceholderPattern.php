<?php

namespace Tent\Utils;

use InvalidArgumentException;

/**
 * Compiles and matches `:placeholder`-style route patterns against request
 * paths, and substitutes captured values back into path templates.
 *
 * Supports three placeholder families, resolved by name:
 * - `:slug` or `:<anything>_slug` — `[A-Za-z0-9_-]+`
 * - `:id` or `:<anything>_id` — `[0-9]+`
 * - `:uuid` or `:<anything>_uuid` — canonical UUID shape
 *
 * ## Example
 *
 * ```php
 * $values = PlaceholderPattern::match('/games/:game_slug/photo_upload', '/games/space-invaders/photo_upload');
 * // ['game_slug' => 'space-invaders']
 *
 * PlaceholderPattern::substitute('/games/:game_slug.json', $values);
 * // '/games/space-invaders.json'
 * ```
 */
class PlaceholderPattern
{
    private const SLUG_REGEX = '[A-Za-z0-9_-]+';
    private const ID_REGEX = '[0-9]+';
    private const UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    /**
     * Matches a `:placeholder` pattern against a path, returning the
     * captured placeholder values.
     *
     * @param string $pattern Route pattern, e.g. '/games/:game_slug/photo_upload'.
     * @param string $path    Request path, e.g. '/games/space-invaders/photo_upload'.
     * @return array<string, string>|null Captured values keyed by placeholder name, or null on no match.
     */
    public static function match(string $pattern, string $path): ?array
    {
        $regex = self::compile($pattern);

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $values = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Substitutes `:name` tokens in a template with their captured values.
     *
     * @param string               $template Path template, e.g. '/games/:game_slug.json'.
     * @param array<string,string> $values   Values keyed by placeholder name, as produced by `match()`.
     * @return string
     */
    public static function substitute(string $template, array $values): string
    {
        return preg_replace_callback(
            '/:([A-Za-z0-9_]+)/',
            function (array $matches) use ($values) {
                $name = $matches[1];

                return $values[$name] ?? $matches[0];
            },
            $template
        );
    }

    /**
     * Compiles a `:placeholder` pattern into an anchored regex.
     *
     * @param string $pattern Route pattern.
     * @return string Anchored regex, ready for `preg_match()`.
     */
    private static function compile(string $pattern): string
    {
        $segments = explode('/', $pattern);

        $compiled = array_map(function (string $segment) {
            if ($segment !== '' && $segment[0] === ':') {
                return self::compileSegment(substr($segment, 1));
            }

            return preg_quote($segment, '/');
        }, $segments);

        return '/^' . implode('\/', $compiled) . '$/';
    }

    /**
     * Compiles a single `:name` segment into a named capture group.
     *
     * @param string $name Placeholder name (without the leading colon).
     * @return string Named capture group regex fragment.
     */
    private static function compileSegment(string $name): string
    {
        $characterClass = self::characterClassFor($name);

        return '(?P<' . $name . '>' . $characterClass . ')';
    }

    /**
     * Resolves the character class regex for a placeholder name.
     *
     * @param string $name Placeholder name.
     * @return string Regex character class.
     */
    private static function characterClassFor(string $name): string
    {
        if ($name === 'slug' || str_ends_with($name, '_slug')) {
            return self::SLUG_REGEX;
        }

        if ($name === 'id' || str_ends_with($name, '_id')) {
            return self::ID_REGEX;
        }

        if ($name === 'uuid' || str_ends_with($name, '_uuid')) {
            return self::UUID_REGEX;
        }

        throw new InvalidArgumentException(sprintf("Unsupported placeholder ':%s'.", $name));
    }
}
