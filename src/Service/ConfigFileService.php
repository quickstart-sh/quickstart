<?php

namespace App\Service;

use App\Entity\Config;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigFileService {
    public const CONFIG_FILE = ".quickstart.yml";

    /**
     * Development aid - turn on echo on all major code paths
     */
    private const DEBUG_ME = false;
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $params;
    /**
     * @var InputInterface
     */
    private InputInterface $input;
    /**
     * @var OutputInterface
     */
    private OutputInterface $output;


    public function __construct(ParameterBagInterface $params) {
        $this->params = $params;
    }

    public function ask(Config $config, string $path, array $optionConfig) {
        $helper = new QuestionHelper();
        //Check if we are allowed to ask the question
        if (array_key_exists("if", $optionConfig) && ConditionEvaluatorService::evaluate($optionConfig["if"], $config) === false) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("Question $path, passing as condition " . $optionConfig["if"] . " returned false");
            //@codeCoverageIgnoreEnd
            return;
        }
        //Is an answer to this question mandatory?
        $mandatory = (array_key_exists("mandatory", $optionConfig) && $optionConfig["mandatory"] === true);
        //@codeCoverageIgnoreStart
        if (self::DEBUG_ME) echo("Question $path, mandatory: " . ($mandatory ? "true" : "false") . "\n");
        //@codeCoverageIgnoreEnd
        //Check if the path has an override set (e.g. OS versions)
        if (array_key_exists("pathOverride", $optionConfig)) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("Question $path, overriding path with " . $optionConfig["pathOverride"]);
            //@codeCoverageIgnoreEnd
            $path = $optionConfig["pathOverride"];
        }
        //Get the current value in the config (null, if the key is not present)
        $currentValue = $config->get($path);
        //@codeCoverageIgnoreStart
        if (self::DEBUG_ME) {
            if ($currentValue == null)
                echo "Question $path, current value is null\n";
            elseif (is_string($currentValue))
                echo "Question $path, current value is " . $currentValue . "\n";
            elseif (is_array($currentValue))
                echo "Question $path, current value is [" . implode(", ", $currentValue) . "]\n";
        }
        //@codeCoverageIgnoreEnd
        //Is this question final (if a value already exists, it can not be modified again)?
        $final = (array_key_exists("final", $optionConfig) && $optionConfig["final"] === true);
        if ($final === true && $currentValue !== null) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("Question $path, passing as it's final and a value");
            //@codeCoverageIgnoreEnd
            return;
        }
        //Get the default value(s)
        $default = array_key_exists("default", $optionConfig) ? $optionConfig["default"] : null;
        //Support dynamic evaluation of default parameters
        if ($default != null && array_key_exists("defaultEval", $optionConfig) && $optionConfig["defaultEval"]) {
            $default = eval("return ($default);");
        }

        switch ($optionConfig["type"]) {
            case "banner":
                $io = new SymfonyStyle($this->input, $this->output);

                $io->title($optionConfig["description"]);
                return;
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
                    $newValue = $helper->ask($this->input, $this->output, new Question($prompt, $questionDefault));
                    if (!$mandatory) //Not mandatory? Use whatever we got in return, including null (which will unset the config value)
                        break;
                    if ($currentValue === null && $newValue === null) {
                        $this->output->writeln("Invalid answer " . $newValue . ", please try again");
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
                        //@codeCoverageIgnoreEnd
                    } elseif (
                        ($currentValue !== null && $newValue !== null) ||
                        ($currentValue === null && $newValue !== null)
                    ) {
                        //Accept the new value
                        break;
                    }
                }
                break;
            case "select_single":
                //@codeCoverageIgnoreStart
                if (self::DEBUG_ME) echo "\n";
                //@codeCoverageIgnoreEnd
                $options = $optionConfig["options"];
                //Check if options is a string - which means this is a dynamic list from a shared yaml (php modules)
                if (is_string($options)) {
                    $options = [];
                    foreach ($this->params->get($optionConfig["options"]) as $key => $data)
                        $options[$key] = $data["name"];
                }
                //Check if we have configurations for individual options (e.g. hide depending on config)
                if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"])) {
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo "Have option-specific configurations\n";
                    //@codeCoverageIgnoreEnd
                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if (is_array($specificConfiguration) && array_key_exists("if", $specificConfiguration)) {
                            $evaluatorResult = ConditionEvaluatorService::evaluate($specificConfiguration["if"], $config);
                            if ($evaluatorResult === false && array_key_exists($specificOption, $options)) {
                                //@codeCoverageIgnoreStart
                                if (self::DEBUG_ME) echo "Removing option $specificOption due to failed condition\n";
                                //@codeCoverageIgnoreEnd
                                unset($options[$specificOption]);
                            }
                        }
                        if (is_array($specificConfiguration) && array_key_exists("default_if", $specificConfiguration)) {
                            $evaluatorResult = ConditionEvaluatorService::evaluate($specificConfiguration["default_if"], $config);
                            if ($evaluatorResult === true) {
                                //@codeCoverageIgnoreStart
                                if (self::DEBUG_ME) echo "Setting option $specificOption as new default\n";
                                //@codeCoverageIgnoreEnd
                                $default = $specificOption;
                                $optionConfig["default"] = $default;
                            }
                        }
                    }
                }
                $prompt = "Please select " . $optionConfig["description"];
                if ($currentValue !== null) {
                    //Check if the current value is still present in the set of options
                    //A case that might happen is e.g. an OS version being dropped
                    if (!array_key_exists($currentValue, $options)) {
                        //@codeCoverageIgnoreStart
                        if (self::DEBUG_ME) echo("Old value " . $currentValue . " does not exist any more, replace with null\n");
                        //@codeCoverageIgnoreEnd
                        $currentValue = null;
                    }
                }
                if ($currentValue === null && array_key_exists("default", $optionConfig)) {
                    $currentValue = $optionConfig["default"];
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo("Replaced empty value with " . $currentValue);
                    //@codeCoverageIgnoreEnd
                }
                if ($currentValue != null && $currentValue != "" && $currentValue != "_none") {
                    $prompt .= " (current: " . $optionConfig["options"][$currentValue] . ")";
                }
                $prompt .= ": ";
                if ($mandatory === false) {
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo("Add none option");
                    //@codeCoverageIgnoreEnd
                    if ($currentValue === null) {
                        $options["_none"] = "None (default)";
                        $currentValue = "_none";
                    } else {
                        $options["_none"] = "None";
                    }
                }
                if ($default !== null) {
                    foreach ($options as $k => $v) {
                        if ($k == $default)
                            $options[$k] = $v . " (default)";
                    }
                }

                $questionDefault = $currentValue == "" ? null : array_search($currentValue, array_keys($options));
                $questionOptions = array_values($options);
                //@codeCoverageIgnoreStart
                if (self::DEBUG_ME) {
                    echo "default is " . $questionDefault . "\n";
                    echo "options are [";
                    foreach ($questionOptions as $k => $v) {
                        echo "$k => $v,";
                    }
                    echo "]\n";
                }
                //@codeCoverageIgnoreEnd
                $answer = $helper->ask(
                    $this->input,
                    $this->output,
                    new ChoiceQuestion(
                        $prompt,
                        $questionOptions,
                        $questionDefault
                    )
                );

                $newValue = array_search(
                    $answer,
                    $options
                );
                if ($newValue == "_none")
                    $newValue = null;
                //Check if we have configurations for individual options (e.g. set other config keys)
                if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"])) {
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo "Have option-specific configurations\n";
                    //@codeCoverageIgnoreEnd
                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if ($specificOption !== $newValue)
                            continue;
                        if (is_array($specificConfiguration) && array_key_exists("set", $specificConfiguration)) {
                            foreach ($specificConfiguration["set"] as $key => $effect) {
                                if ($effect === null) {
                                    //@codeCoverageIgnoreStart
                                    if (self::DEBUG_ME) echo "Unsetting config key $key due to side effect of $specificOption\n";
                                    //@codeCoverageIgnoreEnd
                                    $config->unset($key);
                                } else {
                                    //@codeCoverageIgnoreStart
                                    if (self::DEBUG_ME) echo "Setting config key $key due to side effect of $specificOption\n";
                                    //@codeCoverageIgnoreEnd
                                    $config->set($key, $effect);
                                }
                            }
                        }
                    }
                }
                break;
            case "select_multi":
                //@codeCoverageIgnoreStart
                if (self::DEBUG_ME) echo "\n";
                //@codeCoverageIgnoreEnd
                $options = $optionConfig["options"];
                //Check if options is a string - which means this is a dynamic list from a shared yaml (php modules)
                if (is_string($options)) {
                    $options = [];
                    foreach ($this->params->get($optionConfig["options"]) as $key => $data)
                        $options[$key] = $data["name"];
                }
                //Check if we have configurations for individual options (e.g. hide depending on config)
                if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"])) {
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo "Have option-specific configurations\n";
                    //@codeCoverageIgnoreEnd
                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if (is_array($specificConfiguration) && array_key_exists("if", $specificConfiguration)) {
                            $evaluatorResult = ConditionEvaluatorService::evaluate($specificConfiguration["if"], $config);
                            if ($evaluatorResult === false && array_key_exists($specificOption, $options)) {
                                //@codeCoverageIgnoreStart
                                if (self::DEBUG_ME) echo "Removing option $specificOption due to failed condition\n";
                                //@codeCoverageIgnoreEnd
                                unset($options[$specificOption]);
                            }
                        }
                    }
                }
                $prompt = "Please select " . $optionConfig["description"];
                if (is_array($currentValue) && sizeof($currentValue) > 0) {
                    //Check if the current values are still present in the set of options
                    //A case that might happen is e.g. PHP modules being dropped
                    foreach ($currentValue as $i => $currentKey) {
                        if (!array_key_exists($currentKey, $options)) {
                            //@codeCoverageIgnoreStart
                            if (self::DEBUG_ME) echo("Old value " . $currentKey . " does not exist any more, removing it\n");
                            //@codeCoverageIgnoreEnd
                            unset($currentValue[$i]);
                        }
                    }
                    $currentValue = array_values($currentValue);
                }
                if (is_array($currentValue) && sizeof($currentValue) > 0) {
                    $currentValueLabels = [];
                    foreach ($currentValue as $key)
                        $currentValueLabels[] = $options[$key];
                    $prompt .= " (current: " . implode(",", $currentValueLabels) . ")";
                }
                $prompt .= ": ";
                //Remove all currently set options from offer
                foreach ($options as $k => $v) {
                    if (is_array($currentValue) && in_array($k, $currentValue)) {
                        //@codeCoverageIgnoreStart
                        if (self::DEBUG_ME) echo "discarding $k as it is currently set\n";
                        //@codeCoverageIgnoreEnd
                        unset($options[$k]);
                    }
                }
                if ($mandatory === false || (is_array($currentValue) && sizeof($currentValue) > 0)) {
                    //@codeCoverageIgnoreStart
                    if (self::DEBUG_ME) echo("Add none option\n");
                    //@codeCoverageIgnoreEnd
                    $options["_none"] = "None (default)";
                }
                $questionOptions = array_values($options);
                $questionDefaultString = $mandatory ? "" : (sizeof($questionOptions) - 1);
                //@codeCoverageIgnoreStart
                if (self::DEBUG_ME) {
                    echo "options are [";
                    foreach ($questionOptions as $k => $v) {
                        echo "$k => $v,";
                    }
                    echo "]\n";
                    echo "default is $questionDefaultString\n";
                }
                //@codeCoverageIgnoreEnd
                $answer = $helper->ask(
                    $this->input,
                    $this->output,
                    (new ChoiceQuestion(
                        $prompt,
                        $questionOptions,
                        $questionDefaultString
                    ))->setMultiselect(true)
                );
                $newValue = $currentValue;
                foreach ($answer as $answerLabel) {
                    $key = array_search($answerLabel, $options);
                    if ($key === "_none")
                        continue;
                    $newValue[] = $key;
                    //Check if we have configurations for individual options (e.g. set other config keys)
                    if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"]) && array_key_exists($key, $optionConfig["optionsConfiguration"])) {
                        //@codeCoverageIgnoreStart
                        if (self::DEBUG_ME) echo "Have option-specific configurations\n";
                        //@codeCoverageIgnoreEnd
                        $specificConfiguration = $optionConfig["optionsConfiguration"][$key];
                        if (is_array($specificConfiguration) && array_key_exists("set", $specificConfiguration)) {
                            foreach ($specificConfiguration["set"] as $effectKey => $effect) {
                                if ($effect === null) {
                                    //@codeCoverageIgnoreStart
                                    if (self::DEBUG_ME) echo "Unsetting config key $effectKey due to side effect of $key\n";
                                    //@codeCoverageIgnoreEnd
                                    $config->unset($effectKey);
                                } else {
                                    //@codeCoverageIgnoreStart
                                    if (self::DEBUG_ME) echo "Setting config key $effectKey due to side effect of $key\n";
                                    //@codeCoverageIgnoreEnd
                                    $config->set($effectKey, $effect);
                                }
                            }
                        }

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

    /**
     * @param InputInterface $input
     */
    public function setInput(InputInterface $input): void {
        $this->input = $input;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void {
        $this->output = $output;
    }

}