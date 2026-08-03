<?php

namespace Tent\Tests\Cache\QueryRequestHasher;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Cache\QueryRequestHasher;

class QueryRequestHasherBuildTest extends TestCase
{
    public function testBuildWithEmptyParamsReturnsQueryRequestHasher()
    {
        $hasher = QueryRequestHasher::build([]);

        $this->assertInstanceOf(QueryRequestHasher::class, $hasher);
    }

    public function testBuildIgnoresExtraParams()
    {
        $hasher = QueryRequestHasher::build([
            'class' => QueryRequestHasher::class,
            'unexpected' => 'value',
        ]);

        $this->assertInstanceOf(QueryRequestHasher::class, $hasher);
    }
}
