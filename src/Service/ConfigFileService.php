<?php

namespace App\Service;

use App\Entity\Config;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class ConfigFileService {
    public const CONFIG_FILE = ".quickstart.yml";

    public static function ask(Config $config, string $path, array $optionConfig, InputInterface $input, OutputInterface $output) {
        $helper = new QuestionHelper();
        //Check if we are allowed to ask the question
        if (array_key_exists("if", $optionConfig) && ConditionEvaluatorService::evaluate($optionConfig["if"], $config) === false) {
            //$output->writeln("Question $path, passing as condition ".$optionConfig["if"]." returned false");
            return;
        }
        //Is an answer to this question mandatory?
        $mandatory = (array_key_exists("mandatory", $optionConfig) && $optionConfig["mandatory"] === true);
        //$output->writeln("Question $path, mandatory: ".($mandatory?"true":"false"));
        //Check if the path has an override set (e.g. OS versions)
        if (array_key_exists("pathOverride", $optionConfig)) {
            //$output->writeln("Question $path, overriding path with ".$optionConfig["pathOverride"]);
            $path = $optionConfig["pathOverride"];
        }
        //Get the current value in the config (null, if the key is not present)
        $currentValue = $config->get($path);
        //Is this question final (if a value already exists, it can not be modified again)?
        $final = (array_key_exists("final", $optionConfig) && $optionConfig["final"] === true);
        if ($final === true && $currentValue !== null) {
            //$output->writeln("Question $path, passing as it's final and a value");
            return;
        }
        //Get the default value(s)
        $default = array_key_exists("default", $optionConfig) ? $optionConfig["default"] : null;

        switch ($optionConfig["type"]) {
            case "string":
                $prompt = "Please enter " . $optionConfig["description"] . "";
                $promptAdditions = [];
                if (array_key_exists("defaultDescription", $optionConfig))
                    $promptAdditions[] = "default: " . $optionConfig["defaultDescription"];
                if ($currentValue != "")
                    $promptAdditions[] = "current: " . $currentValue;
                if (sizeof($promptAdditions) > 0)
                    $prompt .= " (" . implode(", ", $promptAdditions) . ")";
                $prompt .= ": ";
                while (true) {
                    $questionDefault = $mandatory ? (($currentValue === null) ? $default : $currentValue) : null;
                    $newValue = $helper->ask($input, $output, new Question($prompt, $questionDefault));
                    if (!$mandatory) //Not mandatory? Use whatever we got in return, including null (which will unset the config value)
                        break;
                    if ($currentValue === null && $newValue === null) {
                        $output->writeln("Invalid answer " . $newValue . ", please try again");
                    } elseif ($currentValue !== null && $newValue === null) {
                        /*
                         * Unreachable. Since we're here,
                         * - mandatory is true
                         * - questionDefault is non-null since currentValue is non-null (default doesn't matter)
                         *
                         * As a result, even an empty input will result in newValue being non-null
                         */
                        //@codeCoverageIgnoreStart
                        return;
                        //codeCoverageIgnoreEnd
                    } elseif (
                        ($currentValue !== null && $newValue !== null) ||
                        ($currentValue === null && $newValue !== null)
                    ) {
                        //Accept the new value
                        break;
                    }
                }
                break;

            default:
                throw new \Exception("Type undefined");
        }
        if ($newValue !== null) {
            $config->set($path, $newValue);
        } else if ($config->has($path)) {
            $config->unset($path);
        }
    }

    public function initialize($fileName) {
        $this->persist($fileName, new Config());
    }

    public function load(string $fileName): Config {
        if (!is_file($fileName))
            throw new \Exception($fileName . " does not exist here.");
        $config = Yaml::parseFile($fileName);
        if (!is_array($config))
            throw new \Exception("Configuration file broken");
        return new Config(array_replace_recursive((new Config())->getAll(), $config));
    }

    public function persist(string $fileName, Config $config) {
        $payload = Yaml::dump($config->getAll()) . "\n";
        if (@file_put_contents($fileName, $payload) === false)
            throw new \Exception("Failed to persist config to " . $fileName);
    }
}