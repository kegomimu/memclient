<?php

namespace Tests;

use MClient\Memcached;
use MClient\MemcachedInterface;
use PHPUnit\Framework\TestCase;

class MemcachedTest extends TestCase
{
    public function testCreation()
    {
        $memcached = new Memcached();
        $this->assertInstanceOf(MemcachedInterface::class, $memcached);
        $memcached->flushAll();
        return $memcached;
    }

    /**
     * @depends testCreation
     */
    public function testSetValue(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->set("testing", "Some value", 180));
        $this->assertTrue($memcached->set("test", "Some - \ntext\n", 180));
        $this->assertTrue($memcached->set("zero", "0", 180));
        $this->assertFalse($memcached->set("incorrect key", "Something", 180));
        return $memcached;
    }

    /**
     * @depends testSetValue
     */
    public function testGetValue(MemcachedInterface $memcached)
    {
        $this->assertEquals("Some value", $memcached->get("testing"));
        $this->assertEquals("Some - \ntext\n", $memcached->get("test"));
        $this->assertEquals("0", $memcached->get("zero"));
        $this->assertEmpty($memcached->get("nonexistent"));
        $this->assertEqualsCanonicalizing(
            [
                "testing" => "Some value",
                "test" => "Some - \ntext\n"
            ],
            $memcached->get(["testing", "test"])
        );
        return $memcached;
    }

    /**
     * @depends testGetValue
     */
    public function testAddValue(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->add("newkey", "new value", 180));
        $this->assertFalse($memcached->add("newkey", "should not overwrite", 180));
        $this->assertEquals("new value", $memcached->get("newkey"));
        return $memcached;
    }

    /**
     * @depends testAddValue
     */
    public function testReplaceValue(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->replace("newkey", "replaced value", 180));
        $this->assertEquals("replaced value", $memcached->get("newkey"));
        $this->assertFalse($memcached->replace("nonexistent", "value", 180));
        return $memcached;
    }

    /**
     * @depends testReplaceValue
     */
    public function testAppendPrependValue(MemcachedInterface $memcached)
    {
        $memcached->set("aptest", "middle", 180);
        $this->assertTrue($memcached->append("aptest", "-end"));
        $this->assertTrue($memcached->prepend("aptest", "start-"));
        $this->assertEquals("start-middle-end", $memcached->get("aptest"));
        $this->assertFalse($memcached->append("nonexistent", "value"));
        $this->assertFalse($memcached->prepend("nonexistent", "value"));
        return $memcached;
    }

    /**
     * @depends testAppendPrependValue
     */
    public function testGetsAndCas(MemcachedInterface $memcached)
    {
        $memcached->set("castest", "original", 180);
        $result = $memcached->gets("castest");
        $this->assertEquals("original", $result["value"]);
        $this->assertIsInt($result["cas"]);
        $this->assertTrue($memcached->cas("castest", "updated", $result["cas"], 180));
        $this->assertEquals("updated", $memcached->get("castest"));
        // The cas token is now stale, so reusing it must fail.
        $this->assertFalse($memcached->cas("castest", "should not apply", $result["cas"], 180));
        $this->assertFalse($memcached->cas("nonexistent", "value", 12345, 180));
        return $memcached;
    }

    /**
     * @depends testGetsAndCas
     */
    public function testTouch(MemcachedInterface $memcached)
    {
        $memcached->set("touchtest", "value", 5);
        $this->assertTrue($memcached->touch("touchtest", 180));
        $this->assertFalse($memcached->touch("nonexistent", 180));
        return $memcached;
    }

    /**
     * @depends testTouch
     */
    public function testGatAndGats(MemcachedInterface $memcached)
    {
        $memcached->set("gattest", "gatvalue", 5);
        $this->assertEquals("gatvalue", $memcached->gat(180, "gattest"));

        $memcached->set("gatstest", "gatsvalue", 5);
        $result = $memcached->gats(180, "gatstest");
        $this->assertEquals("gatsvalue", $result["value"]);
        $this->assertIsInt($result["cas"]);
        return $memcached;
    }

    /**
     * @depends testGatAndGats
     */
    public function testIncrementAndDecrement(MemcachedInterface $memcached)
    {
        $memcached->set("counter", "10", 180);
        $this->assertEquals(11, $memcached->increment("counter"));
        $this->assertEquals(16, $memcached->increment("counter", 5));
        $this->assertEquals(10, $memcached->decrement("counter", 6));
        // Decrementing below zero clamps to zero rather than going negative.
        $this->assertEquals(0, $memcached->decrement("counter", 1000));
        $this->assertFalse($memcached->increment("nonexistent"));
        $memcached->set("notnumeric", "abc", 180);
        $this->assertFalse($memcached->increment("notnumeric"));
        return $memcached;
    }

    /**
     * @depends testIncrementAndDecrement
     */
    public function testDeleteValue(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->delete("test"));
        $this->assertTrue($memcached->delete("nonexistent"));
        $this->assertTrue($memcached->delete("testing"));
        return $memcached;
    }

    /**
     * @depends testDeleteValue
     */
    public function testFlushAll(MemcachedInterface $memcached)
    {
        $memcached->set("tobeflushed", "value", 180);
        $this->assertTrue($memcached->flushAll());
        $this->assertEmpty($memcached->get("tobeflushed"));
        return $memcached;
    }

    /**
     * @depends testFlushAll
     */
    public function testVersion(MemcachedInterface $memcached)
    {
        $version = $memcached->version();
        $this->assertIsString($version);
        $this->assertMatchesRegularExpression("/^\d+\.\d+\.\d+/", $version);
        return $memcached;
    }

    /**
     * @depends testVersion
     */
    public function testVerbosity(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->verbosity(1));
        return $memcached;
    }

    /**
     * @depends testVerbosity
     */
    public function testStats(MemcachedInterface $memcached)
    {
        $stats = $memcached->stats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey("pid", $stats);
        $this->assertArrayHasKey("version", $stats);
        return $memcached;
    }

    /**
     * @depends testStats
     */
    public function testAsyncSet(MemcachedInterface $memcached)
    {
        $memcached->async(true);
        $this->assertTrue($memcached->set("async_test", "Memcached", 10));
        $this->assertTrue($memcached->set("key", "Value", 10));
        $text = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam molestie nisi a dolor
             fringilla. Nunc massa nisi, dignissim ac tempor sed, rutrum a neque. Mauris et diam congue,
             urna vitae, sagittis ante. Sed id fringilla justo. Aliquam vitae varius ante.
             nt in sem nibh. Curabitur vitae erat sed urna lacinia molestie. In viverra mollis diam at blandit.
             arius eu arcu vel commodo. Vestibulum eget metus eu risus faucibus ultrices.
             am aliquam lectus risus, eu aliquam ex fermentum in. 
             am pellentesque non tellus quis fringilla. Etiam condimentum est purus,
             rutrum mauris lacinia vitae. Aenean aliquam nulla tellus, nec volutpat dui placerat eu.
             am ex enim, eleifend ultricies laoreet sit amet, vulputate et ex.
             nec sit amet finibus odio, rutrum congue lorem. Nunc facilisis gravida velit,
             el ullamcorper ex tempus vitae. Proin a risus vitae libero feugiat rhoncus placerat
             eu justo. Duis porta nec mi eu commodo. Suspendisse diam risus, pellentesque ut consequat id,
             mentum vel diam. Cras iaculis mi nec porta semper.";
        $this->assertTrue($memcached->set("big_value", $text, 10));
        return $memcached;
    }

    /**
     * @depends testAsyncSet
     */
    public function testAsyncGet(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->get("async_test"));
        $this->assertTrue($memcached->get("key"));
        $this->assertTrue($memcached->get("big_value"));
        $expected = [
            "async_test" => "Memcached",
            "key" => "Value",
            "big_value" => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam molestie nisi a dolor
             fringilla. Nunc massa nisi, dignissim ac tempor sed, rutrum a neque. Mauris et diam congue,
             urna vitae, sagittis ante. Sed id fringilla justo. Aliquam vitae varius ante.
             nt in sem nibh. Curabitur vitae erat sed urna lacinia molestie. In viverra mollis diam at blandit.
             arius eu arcu vel commodo. Vestibulum eget metus eu risus faucibus ultrices.
             am aliquam lectus risus, eu aliquam ex fermentum in. 
             am pellentesque non tellus quis fringilla. Etiam condimentum est purus,
             rutrum mauris lacinia vitae. Aenean aliquam nulla tellus, nec volutpat dui placerat eu.
             am ex enim, eleifend ultricies laoreet sit amet, vulputate et ex.
             nec sit amet finibus odio, rutrum congue lorem. Nunc facilisis gravida velit,
             el ullamcorper ex tempus vitae. Proin a risus vitae libero feugiat rhoncus placerat
             eu justo. Duis porta nec mi eu commodo. Suspendisse diam risus, pellentesque ut consequat id,
             mentum vel diam. Cras iaculis mi nec porta semper."
        ];
        $this->assertEqualsCanonicalizing($expected, $memcached->receive());
        return $memcached;
    }

    /**
     * @depends testAsyncGet
     */
    public function testAsyncGets(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->gets("async_test"));
        $result = $memcached->receive();
        $this->assertEquals("Memcached", $result["async_test"]["value"]);
        $this->assertIsInt($result["async_test"]["cas"]);
        return $memcached;
    }

    /**
     * @depends testAsyncGets
     */
    public function testAsyncDelete(MemcachedInterface $memcached)
    {
        $this->assertTrue($memcached->delete("key"));
        $this->assertTrue($memcached->delete("async_test"));
        $this->assertTrue($memcached->delete("big_value"));
        $memcached->async(false);
        return $memcached;
    }

    /**
     * @depends testAsyncDelete
     */
    public function testQuit(MemcachedInterface $memcached)
    {
        $memcached->quit();
        $this->assertTrue(true);
    }
}