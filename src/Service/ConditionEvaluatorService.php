<?php

namespace App\Service;

use App\Entity\Config;

/**
 * Class ConditionEvaluatorService
 *
 * Provide a wrapper for evaluating conditions against a Config object
 * @package App\Service
 */
class ConditionEvaluatorService {
    /**
     * Evaluate a condition against the specified configuration
     *
     * Use #path# as a placeholder for the value of the config under the key "path"
     * @param string $condition
     * @param Config $config
     * @return bool
     */
    public static function evaluate(string $condition, Config $config) : bool {
        $condition=preg_replace('/#(.*?)#/m','\\$config->get("$1")',$condition);
        return eval("return ($condition);");
    }
}