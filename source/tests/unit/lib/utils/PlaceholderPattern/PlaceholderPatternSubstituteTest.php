<?php

namespace Tent\Tests\Utils\PlaceholderPattern;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\PlaceholderPattern;

class PlaceholderPatternSubstituteTest extends TestCase
{
    public function testSubstitutesSinglePlaceholder()
    {
        $result = PlaceholderPattern::substitute('/games/:game_slug.json', ['game_slug' => 'space-invaders']);

        $this->assertSame('/games/space-invaders.json', $result);
    }

    public function testSubstitutesMultiplePlaceholders()
    {
        $result = PlaceholderPattern::substitute(
            '/games/:game_slug/photos/:photo_id.json',
            ['game_slug' => 'space-invaders', 'photo_id' => '7']
        );

        $this->assertSame('/games/space-invaders/photos/7.json', $result);
    }

    public function testLeavesTemplateUnchangedWhenNoPlaceholders()
    {
        $result = PlaceholderPattern::substitute('/games.json', ['game_slug' => 'space-invaders']);

        $this->assertSame('/games.json', $result);
    }
}
