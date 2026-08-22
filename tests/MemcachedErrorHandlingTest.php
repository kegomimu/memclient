<?php

namespace Tests;

use MClient\Memcached;
use MClient\MemcachedException;
use PHPUnit\Framework\TestCase;

class MemcachedErrorHandlingTest extends TestCase
{
    public function testInvalidHostThrowsInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Memcached("");
    }

    public function testInvalidPortThrowsInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Memcached("127.0.0.1", 70000);
    }

    public function testInvalidTimeoutThrowsInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Memcached("127.0.0.1", 11211, 0);
    }

    public function testConnectionRefusedThrowsMemcachedException()
    {
        $this->expectException(MemcachedException::class);
        // Nothing should be listening on this port.
        new Memcached("127.0.0.1", 19999, 300);
    }

    public function testUnresolvableHostThrowsMemcachedException()
    {
        $this->expectException(MemcachedException::class);
        new Memcached("this.host.should.not.resolve.invalid", 11211, 300);
    }

    public function testUsingConnectionAfterQuitThrows()
    {
        $memcached = new Memcached();
        $memcached->quit();
        $this->expectException(MemcachedException::class);
        $memcached->set("key", "value", 30);
    }

    public function testQuitIsSafeToCallTwice()
    {
        $memcached = new Memcached();
        $memcached->quit();
        $memcached->quit();
        $this->assertTrue(true);
    }

    public function testNumericParameterInjectionIsRejected()
    {
        $memcached = new Memcached();

        $this->assertFalse($memcached->set("injkey1", "v", "0\r\nquit"));
        $this->assertFalse($memcached->set("injkey2", "v", 0, "5\r\nquit"));
        $this->assertFalse($memcached->cas("injkey3", "v", "1\r\nquit"));
        $this->assertFalse($memcached->flushAll("0\r\nquit"));
        $this->assertFalse($memcached->verbosity("1\r\nquit"));
        $this->assertNull($memcached->gat("30\r\nquit", "somekey"));
        $this->assertSame([], $memcached->stats("items\r\nquit"));

        // The connection must still be perfectly usable afterwards - none
        // of the rejected attempts should have reached the wire.
        $this->assertTrue($memcached->set("stillhealthy", "ok", 30));
        $this->assertEquals("ok", $memcached->get("stillhealthy"));
        $memcached->delete("stillhealthy");
    }

    public function testMalformedKeyInArrayDoesNotCorruptRequest()
    {
        $memcached = new Memcached();
        $memcached->set("goodkey", "goodvalue", 30);

        $this->assertNull($memcached->get(["goodkey", "bad key with space"]));
        // The connection must still be healthy for the next request.
        $this->assertEquals("goodvalue", $memcached->get("goodkey"));
        $memcached->delete("goodkey");
    }

    public function testValuesContainingProtocolKeywordsRoundTripExactly()
    {
        $memcached = new Memcached();
        $tricky = [
            "trickykey1" => "before\r\nEND\r\nafter",
            "trickykey2" => "VALUE fake 0 999\r\nnotreal",
            "trickykey3" => "oops CLIENT_ERROR nope\r\nSERVER_ERROR also nope",
        ];
        foreach ($tricky as $key => $value) {
            $this->assertTrue($memcached->set($key, $value, 30));
        }
        foreach ($tricky as $key => $expected) {
            $this->assertEquals($expected, $memcached->get($key));
        }
        $this->assertEqualsCanonicalizing($tricky, $memcached->get(array_keys($tricky)));
        foreach (array_keys($tricky) as $key) {
            $memcached->delete($key);
        }
    }

    public function testPipelinedAsyncResponsesAreFullyReassembled()
    {
        $memcached = new Memcached();
        $values = [];
        for ($i = 1; $i <= 20; $i++) {
            $values["pipekey{$i}"] = str_repeat("chunk{$i}-", 2000);
            $memcached->set("pipekey{$i}", $values["pipekey{$i}"], 30);
        }

        $memcached->async(true);
        foreach (array_keys($values) as $key) {
            $memcached->get($key);
        }
        $result = $memcached->receive();
        $memcached->async(false);

        $this->assertIsArray($result);
        $this->assertCount(20, $result);
        $this->assertEqualsCanonicalizing($values, $result);

        foreach (array_keys($values) as $key) {
            $memcached->delete($key);
        }
    }

    public function testIncrementOnNonNumericValueReturnsFalseNotException()
    {
        $memcached = new Memcached();
        $memcached->set("notnumerictest", "abc", 30);
        // This is a documented, normal outcome of increment() - it must
        // not throw.
        $this->assertFalse($memcached->increment("notnumerictest"));
        $memcached->delete("notnumerictest");
    }
}
