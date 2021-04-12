<?php
namespace App\Tests\Unit;
use App\Entity\Config;
use App\Service\ConditionEvaluatorService;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorServiceTest extends TestCase {
    public function testSimpleReturn() {
        $this->assertTrue(ConditionEvaluatorService::evaluate("true",new Config()));
        $this->assertFalse(ConditionEvaluatorService::evaluate("false",new Config()));
    }
    public function testConfigInterface() {
        $config=new Config([
            "foo" => "bar",
            "baz" => [
                "qux",
                "quux",
            ],
            "guide" => [
                "to" => "the",
                "galaxy" => 42
            ]
        ]);
        $this->assertTrue(ConditionEvaluatorService::evaluate("#foo# === 'bar'",$config));
        $this->assertTrue(ConditionEvaluatorService::evaluate("#guide.to# === 'the'",$config));
        $this->assertTrue(ConditionEvaluatorService::evaluate("#guide.galaxy# === 42",$config));
        $this->assertTrue(ConditionEvaluatorService::evaluate("in_array('qux', #baz#)",$config));
        $this->assertFalse(ConditionEvaluatorService::evaluate("in_array('quuux', #baz#)",$config));
    }
}
