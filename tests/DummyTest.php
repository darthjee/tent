
<?php

use PHPUnit\Framework\TestCase;
use Tent\Dummy;

class DummyTest extends TestCase
{
    public function testSayHello()
    {
        $dummy = new Dummy();
        $this->assertEquals("Hello, world!", $dummy->sayHello());
    }
}
?>