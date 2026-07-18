<?php

namespace App\Tests\Unit;

use App\Entity\Config;
use App\Service\ConditionEvaluatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConditionEvaluatorServiceTest extends KernelTestCase {

    public function testSimpleReturn() {
        self::bootKernel();
        $conditionEvaluatorService = static::getContainer()->get(ConditionEvaluatorService::class);
        $this->assertTrue($conditionEvaluatorService->evaluate("true", new Config()));
        $this->assertFalse($conditionEvaluatorService->evaluate("false", new Config()));
    }

    public function testConfigInterface() {
        self::bootKernel();
        $conditionEvaluatorService = static::getContainer()->get(ConditionEvaluatorService::class);
        $config = new Config([
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
        $this->assertTrue($conditionEvaluatorService->evaluate("#foo# === 'bar'", $config));
        $this->assertTrue($conditionEvaluatorService->evaluate("#guide.to# === 'the'", $config));
        $this->assertTrue($conditionEvaluatorService->evaluate("#guide.galaxy# === 42", $config));
        $this->assertTrue($conditionEvaluatorService->evaluate("in_array('qux', #baz#)", $config));
        $this->assertFalse($conditionEvaluatorService->evaluate("in_array('quuux', #baz#)", $config));
    }
}
