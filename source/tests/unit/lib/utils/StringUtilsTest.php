<?php

namespace Tent\Tests\Utils;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Utils\StringUtils;

class StringUtilsTest extends TestCase
{
    public function testToStudlyCaseConvertsSnakeCase()
    {
        $this->assertEquals('BeginsWith', StringUtils::toStudlyCase('begins_with'));
    }

    public function testToStudlyCaseConvertsDashedAndMixedCase()
    {
        $this->assertEquals('EndsWith', StringUtils::toStudlyCase('ENDS-with'));
    }
}
