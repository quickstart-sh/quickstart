<?php

namespace App\Tests\Unit;

use App\Entity\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase {
    public function testKeepsVersion() {
        $config = new Config([
            "version" => 2
        ]);
        $this->assertEquals([
            "version" => 2,
        ], $config->getAll());
    }

    public function testAddsVersion() {
        $config = new Config();
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    public function testSetAllKeepsVersion() {
        $config = new Config();
        $config->setAll([
            "version" => 2
        ]);
        $this->assertEquals([
            "version" => 2,
        ], $config->getAll());
    }

    public function testSetAllAddsVersion() {
        $config = new Config();
        $config->setAll([]);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    public function testSetAll() {
        $config = new Config();
        $config->setAll([
            "foo" => "bar",
            "bar" => [
                "baz",
                "qux",
            ],
        ]);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => "bar",
            "bar" => [
                "baz",
                "qux",
            ],
        ], $config->getAll());
    }

    //Block 1: Top level set / set twice / has / get / unset / unset twice
    public function testSetTopLevel() {
        $config = new Config();
        $config->set("foo", "bar");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => "bar",
        ], $config->getAll());
    }

    public function testSetTopLevelTwice() {
        $config = new Config();
        $config->set("foo", "bar");
        $config->set("foo", "baz");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => "baz",
        ], $config->getAll());
    }

    public function testHasTopLevel() {
        $config = new Config([
            "foo" => "bar",
        ]);
        $this->assertTrue($config->has("foo"));
        $this->assertFalse($config->has("baz"));
    }

    public function testGetTopLevel() {
        $config = new Config([
            "foo" => "bar",
        ]);
        $this->assertEquals("bar", $config->get("foo"));
        $this->assertEquals(null, $config->get("baz"));
    }

    public function testUnsetTopLevel() {
        $config = new Config([
            "foo" => "bar",
            "baz" => "qux"
        ]);
        $config->unset("foo");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "baz" => "qux",
        ], $config->getAll());
    }

    public function testUnsetTopLevelError() {
        $config = new Config([
            "foo" => "bar",
            "baz" => "qux"
        ]);
        $config->unset("foo");
        $this->expectException(\Exception::class);
        $config->unset("foo");
    }

    //Block 2: Two level set / set twice / has / get / unset / unset twice
    public function testSetTwoLevels() {
        $config = new Config();
        $config->set("foo.bar", "baz");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => [
                "bar" => "baz",
            ],
        ], $config->getAll());
    }

    public function testSetTwoLevelsTwice() {
        $config = new Config();
        $config->set("foo.bar", "baz");
        $config->set("foo.bar", "qux");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => [
                "bar" => "qux",
            ],
        ], $config->getAll());
    }

    public function testHasTwoLevels() {
        $config = new Config([
            "foo" => [
                "bar" => "qux"
            ],
        ]);
        $this->assertTrue($config->has("foo.bar"));
        $this->assertFalse($config->has("foo.baz"));
    }

    public function testGetTwoLevels() {
        $config = new Config([
            "foo" => [
                "bar" => "qux"
            ],
        ]);
        $this->assertEquals("qux", $config->get("foo.bar"));
        $this->assertEquals(null, $config->get("foo.baz"));
    }

    public function testUnsetTwoLevels() {
        $config = new Config([
            "foo" => [
                "bar" => "qux"
            ],
        ]);
        $config->unset("foo.bar");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => [
            ],
        ], $config->getAll());
    }

    public function testUnsetTwoLevelsError() {
        $config = new Config([
            "foo" => [
                "bar" => "qux"
            ],
        ]);
        $config->unset("foo.bar");
        $this->expectException(\Exception::class);
        $config->unset("foo.bar");
    }

    //Block 4: Implicit and explicit arrays
    public function testSetImplicitArray() {
        $config = new Config();
        //Implicit array create, explicit push
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "baz" => [
                "qux",
                "quux",
            ],
        ], $config->getAll());
    }

    public function testHasArray() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $this->assertTrue($config->has("baz.[quux]"));
        $this->assertFalse($config->has("baz.[bar]"));
    }

    public function testArrayHasNumericKey() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $this->assertTrue($config->has("baz.1"));
        $this->assertFalse($config->has("baz.3"));
    }

    public function testArrayGetNumericKey() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $this->assertEquals("quux", $config->get("baz.1"));
    }

    public function testArraySetNumericKey() {
        $config = new Config([
            "baz" => [
                "qux",
                "quux",
                "quuux",
            ],
        ]);
        $config->set("baz.1", "bar");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "baz" => [
                "qux",
                "bar",
                "quuux",
            ],
        ], $config->getAll());
    }

    public function testUnsetArrayByValue() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $config->unset("baz.[quux]");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "baz" => [
                "qux",
                "quuux",
            ],
        ], $config->getAll());
    }

    public function testUnsetArrayByValueError() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $this->expectException(\Exception::class);
        $config->unset("baz.[bar]");
    }

    public function testUnsetArrayByKey() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $config->unset("baz.1");
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "baz" => [
                "qux",
                "quuux",
            ],
        ], $config->getAll());
    }

    public function testUnsetArrayByKeyError() {
        $config = new Config();
        $config->set("baz.[]", "qux");
        $config->set("baz.[]", "quux");
        $config->set("baz.[]", "quuux");
        $this->expectException(\Exception::class);
        $config->unset("baz.3");
    }

    public function testSetExplicitArray() {
        $config = new Config();
        //Explicit array set
        $config->set("guide", ["to" => "the", "galaxy" => 42]);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "guide" => [
                "to" => "the",
                "galaxy" => 42
            ]
        ], $config->getAll());
    }

    public function testSetImplicitArrayError() {
        $config = new Config();
        //Implicit array create in middle of path - this should throw an error
        $this->expectException(\Exception::class);
        $config->set("banana.[].monkey", "forest");
    }

    public function testOverwriteWithArrayError() {
        $config = new Config();
        $config->set("foo", "bar");
        //Try to set an array key inside a string - this should throw an error
        $this->expectException(\Exception::class);
        $config->set("foo.baz", "qux");
    }

}