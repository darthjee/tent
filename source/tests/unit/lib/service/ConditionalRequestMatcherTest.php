<?php

namespace Tent\Tests\Service;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Request;
use Tent\Service\ConditionalRequestMatcher;

class ConditionalRequestMatcherTest extends TestCase
{
    private const ETAG = '"abc123"';
    private const MTIME = 1700000000;
    private const MTIME_DATE = 'Tue, 14 Nov 2023 22:13:20 GMT';

    public function testNoHeadersIsModified()
    {
        $this->assertFalse($this->matcher([])->isNotModified());
    }

    public function testIfNoneMatchExactMatch()
    {
        $this->assertTrue($this->matcher(['If-None-Match' => '"abc123"'])->isNotModified());
    }

    public function testIfNoneMatchListMatch()
    {
        $headers = ['If-None-Match' => '"other", "abc123" , "another"'];

        $this->assertTrue($this->matcher($headers)->isNotModified());
    }

    public function testIfNoneMatchWildcard()
    {
        $this->assertTrue($this->matcher(['If-None-Match' => '*'])->isNotModified());
    }

    public function testIfNoneMatchWeakRequestEtag()
    {
        $this->assertTrue($this->matcher(['If-None-Match' => 'W/"abc123"'])->isNotModified());
    }

    public function testIfNoneMatchWeakCurrentEtag()
    {
        $matcher = new ConditionalRequestMatcher(
            new Request(['headers' => ['If-None-Match' => '"abc123"']]),
            'W/"abc123"',
            self::MTIME
        );

        $this->assertTrue($matcher->isNotModified());
    }

    public function testIfNoneMatchMismatch()
    {
        $headers = ['If-None-Match' => '"other", W/"different"'];

        $this->assertFalse($this->matcher($headers)->isNotModified());
    }

    public function testIfNoneMatchMismatchIgnoresMatchingIfModifiedSince()
    {
        $headers = [
            'If-None-Match' => '"other"',
            'If-Modified-Since' => self::MTIME_DATE
        ];

        $this->assertFalse($this->matcher($headers)->isNotModified());
    }

    public function testIfModifiedSinceEqualToMtime()
    {
        $this->assertTrue($this->matcher(['If-Modified-Since' => self::MTIME_DATE])->isNotModified());
    }

    public function testIfModifiedSinceAfterMtime()
    {
        $headers = ['If-Modified-Since' => 'Wed, 15 Nov 2023 00:00:00 GMT'];

        $this->assertTrue($this->matcher($headers)->isNotModified());
    }

    public function testIfModifiedSinceBeforeMtime()
    {
        $headers = ['If-Modified-Since' => 'Tue, 14 Nov 2023 22:13:19 GMT'];

        $this->assertFalse($this->matcher($headers)->isNotModified());
    }

    public function testIfModifiedSinceInvalidDate()
    {
        $this->assertFalse($this->matcher(['If-Modified-Since' => 'not a date'])->isNotModified());
    }

    public function testLowerCaseHeaderNames()
    {
        $this->assertTrue($this->matcher(['if-none-match' => '"abc123"'])->isNotModified());
        $this->assertTrue($this->matcher(['if-modified-since' => self::MTIME_DATE])->isNotModified());
    }

    public function testMixedCaseHeaderNames()
    {
        $this->assertTrue($this->matcher(['IF-NONE-MATCH' => '"abc123"'])->isNotModified());
        $this->assertTrue($this->matcher(['If-modified-Since' => self::MTIME_DATE])->isNotModified());
    }

    private function matcher(array $headers): ConditionalRequestMatcher
    {
        return new ConditionalRequestMatcher(
            new Request(['headers' => $headers]),
            self::ETAG,
            self::MTIME
        );
    }
}
