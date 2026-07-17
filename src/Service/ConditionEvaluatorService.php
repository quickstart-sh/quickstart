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
        $condition = preg_replace('/#(.*?)#/m', '\\$config->get("$1")', $condition);
        //echo "evaluating $condition\n";
        $ret = eval("return ($condition);");
        //var_dump($ret);
        return $ret;
    }
}