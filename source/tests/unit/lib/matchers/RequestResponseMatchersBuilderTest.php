<?php

namespace Tent\Tests\Matchers;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Matchers\RequestResponseMatchersBuilder;
use Tent\Matchers\StatusCodeMatcher;
use Tent\Matchers\RequestMethodMatcher;

class RequestResponseMatchersBuilderTest extends TestCase
{
    public function testBuildWithMatchersAttribute()
    {
        $attributes = [
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => [200, 201]
                ]
            ]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertNotEmpty($matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
    }

    public function testBuildWithDeprecatedHttpCodesAttribute()
    {
        $attributes = [
            'httpCodes' => [200, 201]
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithNoAttributesDefaultsTo200()
    {
        $attributes = [];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithRequestMethodsAttribute()
    {
        $attributes = [
            'requestMethods' => ['POST', 'PUT']
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildWithBothHttpCodesAndRequestMethods()
    {
        $attributes = [
            'httpCodes' => [200, 201],
            'requestMethods' => ['POST', 'PUT']
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        $this->assertIsArray($matchers);
        $this->assertCount(2, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
        $this->assertInstanceOf(RequestMethodMatcher::class, $matchers[1]);
    }

    public function testBuildPrefersMatchersOverHttpCodes()
    {
        $attributes = [
            'matchers' => [
                [
                    'class' => 'Tent\Matchers\StatusCodeMatcher',
                    'httpCodes' => [200, 201]
                ]
            ],
            'httpCodes' => [500, 501]  // This should be ignored
        ];

        $builder = new RequestResponseMatchersBuilder($attributes);
        $matchers = $builder->build();

        // When matchers is present, httpCodes is ignored and requestMethods is not added
        $this->assertIsArray($matchers);
        $this->assertCount(1, $matchers);
        $this->assertInstanceOf(StatusCodeMatcher::class, $matchers[0]);
    }
}
