<?php

namespace App\Entity;

use Exception;

class Config {
    /**
     * Default version of new Config objects.
     *
     * TODO: implement upgrade strategy...
     */
    public const int DEFAULT_VERSION = 1;

    /**
     * @var array Configuration data
     */
    private array $config = [];

    /**
     * Config constructor.
     * @param array $config
     */
    public function __construct(array $config = []) {
        $this->setAll($config);
    }

    /**
     * Replace the whole config
     * @param array $config
     */
    public function setAll(array $config): void {
        //Ensure the version key is always, no matter the source, present and set
        if (!array_key_exists("version", $config))
            $config = array_merge_recursive(["version" => self::DEFAULT_VERSION], $config);
        $this->config = $config;
    }

    /**
     * Set a config key to value
     *
     * path follows loosely the jq syntax. A dot descends into the array at the specified key. [] can be given
     * at the end, this pushes the value into an array at the parent path.
     *
     * Missing array keys are created on demand.
     * @param string $path
     * @param $value
     * @throws Exception
     */
    public function set(string $path, $value): void {
        //echo "\n";
        //echo "setting $path to ".(is_array($value)?print_r($value,true):$value)."\n";
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        //This is set to true in case the .[] syntax is used to force an array.
        $append = false;
        //Back reference for adding multiple things to array
        $parent = null;
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!is_array($current)) {
                throw new Exception("Trying to access $part as array, while it is a " . gettype($current));
            }
            if (!array_key_exists($part, $current)) {
                if ($index == sizeof($parts) - 1) { //last element in path, create it (with default null)
                    if ($part == "[]") {
                        $part = sizeof($current);
                        $append = true;
                    } else {
                        $current[$part] = null;
                    }
                } else {
                    if ($part == "[]") {
                        throw new Exception("[] must only be the last element of the path");
                    }
                    $current[$part] = [];
                }
            }
            $parent =& $current;
            $current =& $current[$part];
        }
        //Now, if there is an array, we can supply either a single thing or an array
        //if it's an array, merge it (=ensure that there is one and exactly one member of that value)
        if ($append && is_array($value)) {
            unset($parent[$part]); //undo the set-to-null from before
            $parent = array_values(array_unique(array_merge($parent, $value)));
        } else if ($append && !is_array($value)) {
            $current = $value;
            $parent = array_values(array_unique($parent));
        } else {
            $current = $value;
        }
        //echo "after:\n";
        //var_dump($this->config);
    }

    /**
     * Get the value of the config key specified
     *
     * @param string $path
     * @return array|mixed|null
     * @see Config::set() for syntax
     */
    public function get(string $path): mixed {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "requested: $path\n";
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                return null;
            }
            $current =& $current[$part];
        }
        //var_dump($current);
        return $current;
    }

    /**
     * Check if the config has a value for the given key
     *
     * In addition to the syntax of Config::set(), you can also search for a value in an array by specifying path[value].
     *
     * Note: this will return false if *any* part of the chain is missing.
     * @param string $path
     * @return bool
     * @see Config::set() for syntax
     */
    public function has(string $path): bool {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                //check if we are searching for a value - has("path[value]")
                if ($index == sizeof($parts) - 1 && str_starts_with($part, "[") && str_ends_with($part, "]")) {
                    $value = substr($part, 1, -1);
                    if (in_array($value, $current) !== false) {
                        return true;
                    }
                }
                return false;
            }
            $current =& $current[$part];
        }
        return true;
    }

    /**
     * Delete a value from the configuration.
     *
     * In addition to the syntax of Config::set(), you can also search for a value in an array by specifying path[value].
     *
     * Note: this will barf if *any* part of the chain is missing.
     * @param string $path
     * @throws Exception
     * @see Config::set() for syntax
     */
    public function unset(string $path): void {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                //check if we are searching for a value - unset("path[value]")
                if (str_starts_with($part, "[") && str_ends_with($part, "]")) {
                    $value = substr($part, 1, -1);
                    $key = array_search($value, $current);
                    if ($key !== false) {
                        array_splice($current, $key, 1);
                        return;
                    }
                }
                throw new Exception("Could not find part $part");
            }
            if ($index == sizeof($parts) - 1) {
                //Check if the array is sequential. If it is, need to re-order it, so use splice instead of unset.
                //See https://stackoverflow.com/a/173479 for the source of this if
                if (is_numeric($part) && array_keys($current) === range(0, count($current) - 1)) {
                    array_splice($current, $part, 1);
                } else {
                    unset($current[$part]);
                }
                return;
            }
            $current =& $current[$part];
        }
    }

    /**
     * Get the whole config
     * @return array
     */
    public function getAll(): array {
        return $this->config;
    }

}