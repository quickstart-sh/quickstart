<?php

namespace App\Service;

use App\Entity\Config;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Class ConditionEvaluatorService
 *
 * Provide a wrapper for evaluating conditions against a Config object
 * @package App\Service
 */
class ConditionEvaluatorService implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * Evaluate a condition against the specified configuration
     *
     * Use #path# as a placeholder for the value of the config under the key "path"
     * @param string $condition
     * @param Config $config
     * @return bool
     */
    public static function evaluate(string $condition, Config $config): bool {
        $this->logger->debug("Evaluating condition", ["condition" => $condition]);
        $condition = preg_replace('/#(.*?)#/m', '\\$config->get("$1")', $condition);
        $this->logger->debug("Condition after placeholder replacement", ["condition" => $condition]);
        try {
            $ret = eval("return ($condition);");
        } catch (Exception $e) {
            $this->logger->error("Error evaluating condition", [
                "condition" => $condition,
                "message" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);
            throw $e;
        }
        $this->logger->debug("Evaluation result", ["result" => $ret]);
        return $ret;
    }
}