<?php

namespace Tent\Tests\Utils\PlaceholderPattern;

require_once __DIR__ . '/../../../../support/loader.php';

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tent\Utils\PlaceholderPattern;

class PlaceholderPatternMatchTest extends TestCase
{
    public function testMatchesSlugPlaceholder()
    {
        $values = PlaceholderPattern::match('/games/:slug', '/games/space-invaders');

        $this->assertSame(['slug' => 'space-invaders'], $values);
    }

    public function testMatchesNamedSlugPlaceholder()
    {
        $values = PlaceholderPattern::match('/games/:game_slug', '/games/space-invaders');

        $this->assertSame(['game_slug' => 'space-invaders'], $values);
    }

    public function testMatchesIdPlaceholder()
    {
        $values = PlaceholderPattern::match('/users/:id', '/users/42');

        $this->assertSame(['id' => '42'], $values);
    }

    public function testMatchesNamedIdPlaceholder()
    {
        $values = PlaceholderPattern::match('/users/:user_id', '/users/42');

        $this->assertSame(['user_id' => '42'], $values);
    }

    public function testMatchesUuidPlaceholder()
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $values = PlaceholderPattern::match('/sessions/:uuid', '/sessions/' . $uuid);

        $this->assertSame(['uuid' => $uuid], $values);
    }

    public function testMatchesNamedUuidPlaceholder()
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $values = PlaceholderPattern::match('/sessions/:session_uuid', '/sessions/' . $uuid);

        $this->assertSame(['session_uuid' => $uuid], $values);
    }

    public function testMatchesMultiplePlaceholders()
    {
        $values = PlaceholderPattern::match(
            '/games/:game_slug/photos/:photo_id',
            '/games/space-invaders/photos/7'
        );

        $this->assertSame(['game_slug' => 'space-invaders', 'photo_id' => '7'], $values);
    }

    public function testReturnsNullForMismatchedLiteralSegment()
    {
        $values = PlaceholderPattern::match('/games/:game_slug/photo_upload', '/games/space-invaders/delete');

        $this->assertNull($values);
    }

    public function testReturnsNullWhenCharacterClassDoesNotMatch()
    {
        $values = PlaceholderPattern::match('/users/:id', '/users/not-a-number');

        $this->assertNull($values);
    }

    public function testReturnsNullForDifferentSegmentCount()
    {
        $values = PlaceholderPattern::match('/games/:game_slug', '/games/space-invaders/photo_upload');

        $this->assertNull($values);
    }

    public function testThrowsForUnsupportedPlaceholderName()
    {
        $this->expectException(InvalidArgumentException::class);

        PlaceholderPattern::match('/games/:foo', '/games/space-invaders');
    }

    public function testMatchesLastSegmentWithFormatSuffix()
    {
        $values = PlaceholderPattern::match(
            '/games/:game_slug/npcs/:character_id.json',
            '/games/space-invaders/npcs/42.json'
        );

        $this->assertSame(['game_slug' => 'space-invaders', 'character_id' => '42'], $values);
    }

    public function testMatchesLastSegmentWithDifferentFormatSuffix()
    {
        $values = PlaceholderPattern::match('/games/:game_slug.xml', '/games/space-invaders.xml');

        $this->assertSame(['game_slug' => 'space-invaders'], $values);
    }

    public function testReturnsNullWhenFormatSuffixDoesNotMatch()
    {
        $values = PlaceholderPattern::match(
            '/games/:game_slug/npcs/:character_id.json',
            '/games/space-invaders/npcs/42.xml'
        );

        $this->assertNull($values);
    }

    public function testReturnsNullWhenFormatSuffixIsMissing()
    {
        $values = PlaceholderPattern::match(
            '/games/:game_slug/npcs/:character_id.json',
            '/games/space-invaders/npcs/42'
        );

        $this->assertNull($values);
    }

    public function testThrowsWhenFormatSuffixAppearsOnNonLastSegment()
    {
        $this->expectException(InvalidArgumentException::class);

        PlaceholderPattern::match(
            '/games/:game_slug.json/npcs/:character_id',
            '/games/space-invaders.json/npcs/42'
        );
    }
}
