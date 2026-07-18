<?php

namespace App\Service;

use App\Entity\Config;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigFileService implements LoggerAwareInterface {
    use LoggerAwareTrait;

    public const string CONFIG_FILE = ".quickstart.yml";

    /**
     * @var InputInterface
     */
    private InputInterface $input;
    /**
     * @var OutputInterface
     */
    private OutputInterface $output;

    public function __construct(private readonly ParameterBagInterface $params, private readonly ConditionEvaluatorService $conditionEvaluatorService) {
    }

    public function ask(Config $config, string $path, array $optionConfig): void {
        $this->logger->info("Asking question", ["path" => $path, "config" => $optionConfig]);
        $helper = new QuestionHelper();
        //Check if we are allowed to ask the question
        if (array_key_exists("if", $optionConfig) && $this->conditionEvaluatorService->evaluate($optionConfig["if"], $config) === false) {
            $this->logger->debug("Passing over as condition " . $optionConfig["if"] . " returned false\n");
            return;
        }
        //Is an answer to this question mandatory?
        $mandatory = (array_key_exists("mandatory", $optionConfig) && $optionConfig["mandatory"] === true);
        $this->logger->debug("Mandatory: " . ($mandatory ? "true" : "false") . "\n");

        //Check if the path has an override set (e.g. OS versions)
        if (array_key_exists("pathOverride", $optionConfig)) {
            $this->logger->debug("Overriding path with " . $optionConfig["pathOverride"]);
            $path = $optionConfig["pathOverride"];
        }
        //Get the current value in the config (null, if the key is not present)
        $currentValue = $config->get($path);

        if ($currentValue == null)
            $this->logger->debug("current value is null\n");
        elseif (is_string($currentValue))
            $this->logger->debug("Current value is " . $currentValue . "\n");
        elseif (is_array($currentValue))
            $this->logger->debug("Current value is [" . implode(", ", $currentValue) . "]\n");


        //Is this question final (if a value already exists, it can not be modified again)?
        $final = (array_key_exists("final", $optionConfig) && $optionConfig["final"] === true);
        $this->logger->debug("Final is " . ($final ? "true" : "false") . "\n");
        if ($final === true && $currentValue !== null) {
            $this->logger->debug("Passing over as it's final and already has a value");
            return;
        }

        //Get the default value(s)
        $default = array_key_exists("default", $optionConfig) ? $optionConfig["default"] : null;
        $this->logger->debug("Default: " . $default);
        //Support dynamic evaluation of default parameters
        if ($default != null && array_key_exists("defaultEval", $optionConfig) && $optionConfig["defaultEval"]) {
            $this->logger->debug("Passing default through evaluator");
            $default = eval("return ($default);");
            $this->logger->debug("Default after evaluation pass: " . $default);
        }

        switch ($optionConfig["type"]) {
            case "banner":
                $io = new SymfonyStyle($this->input, $this->output);
                $io->title($optionConfig["description"]);
                return;
            case "string":
                $prompt = "Please enter " . $optionConfig["description"];
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
                $options = $optionConfig["options"];
                //Check if options is a string - which means this is a dynamic list from a shared yaml (php modules)
                if (is_string($options)) {
                    $options = [];
                    foreach ($this->params->get($optionConfig["options"]) as $key => $data)
                        $options[$key] = $data["name"];
                }
                //Check if we have configurations for individual options (e.g. hide depending on config)
                if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"])) {

                    $this->logger->debug("Have option-specific configurations");

                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if (is_array($specificConfiguration) && array_key_exists("if", $specificConfiguration)) {
                            $evaluatorResult = $this->conditionEvaluatorService->evaluate($specificConfiguration["if"], $config);
                            if ($evaluatorResult === false && array_key_exists($specificOption, $options)) {
                                $this->logger->debug("Removing option $specificOption due to failed condition");
                                unset($options[$specificOption]);
                            }
                        }
                        if (is_array($specificConfiguration) && array_key_exists("default_if", $specificConfiguration)) {
                            $evaluatorResult = $this->conditionEvaluatorService->evaluate($specificConfiguration["default_if"], $config);
                            if ($evaluatorResult === true) {
                                $this->logger->debug("Setting option $specificOption as new default");
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
                        $this->logger->debug("Old value " . $currentValue . " does not exist any more, replace with null");
                        $currentValue = null;
                    }
                }
                if ($currentValue === null && array_key_exists("default", $optionConfig)) {
                    $currentValue = $optionConfig["default"];
                    $this->logger->debug("Replaced empty value with " . $currentValue . "\n");
                }
                if ($currentValue != null && $currentValue != "" && $currentValue != "_none" && array_key_exists($currentValue, $options)) {
                    $prompt .= " (current: " . $options[$currentValue] . ")";
                }
                $prompt .= ": ";
                if ($mandatory === false) {
                    $this->logger->debug("Add none option");
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

                $this->logger->debug("Asking question", ["question" => $prompt, "options" => $questionOptions, "default" => $questionDefault]);

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

                    $this->logger->debug("Have option-specific configurations");

                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if ($specificOption !== $newValue)
                            continue;
                        if (is_array($specificConfiguration) && array_key_exists("set", $specificConfiguration)) {
                            foreach ($specificConfiguration["set"] as $key => $effect) {
                                if ($effect === null) {
                                    $this->logger->debug("Unsetting config key $key due to side effect of $specificOption");
                                    if ($config->has($key))
                                        $config->unset($key);
                                } else {
                                    $this->logger->debug("Setting config key $key due to side effect of $specificOption");
                                    $config->set($key, $effect);
                                }
                            }
                        }
                    }
                }
                break;
            case "select_multi":
                $options = $optionConfig["options"];
                //Check if options is a string - which means this is a dynamic list from a shared yaml (php modules)
                if (is_string($options)) {
                    $options = [];
                    foreach ($this->params->get($optionConfig["options"]) as $key => $data)
                        $options[$key] = $data["name"];
                }
                if (!is_array($currentValue))
                    $currentValue = [];
                //Check if we have configurations for individual options (e.g. hide depending on config)
                if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"])) {
                    $this->logger->debug("Have option-specific configurations");
                    foreach ($optionConfig["optionsConfiguration"] as $specificOption => $specificConfiguration) {
                        if (is_array($specificConfiguration) && array_key_exists("if", $specificConfiguration)) {
                            $evaluatorResult = $this->conditionEvaluatorService->evaluate($specificConfiguration["if"], $config);
                            if ($evaluatorResult === false && array_key_exists($specificOption, $options)) {
                                $this->logger->debug("Removing option $specificOption due to failed condition");
                                unset($options[$specificOption]);
                            }
                        }
                        if (is_array($specificConfiguration) && array_key_exists("default_if", $specificConfiguration)) {
                            $evaluatorResult = $this->conditionEvaluatorService->evaluate($specificConfiguration["default_if"], $config);
                            if ($evaluatorResult === true) {
                                $this->logger->debug("Adding option $specificOption as current value");
                                if (!in_array($specificOption, $currentValue))
                                    $currentValue[] = $specificOption;
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
                            $this->logger->debug("Old value " . $currentKey . " does not exist any more, removing it");
                            unset($currentValue[$i]);
                        }
                    }
                    $currentValue = array_values($currentValue);
                }
                if (is_array($currentValue) && sizeof($currentValue) > 0) {
                    $currentValueLabels = [];
                    foreach ($currentValue as $key) {
                        if (array_key_exists($key, $options))
                            $currentValueLabels[] = $options[$key];
                    }
                    $prompt .= " (current: " . implode(",", $currentValueLabels) . ")";
                }
                $prompt .= ": ";
                //Remove all currently set options from offer
                foreach ($options as $k => $v) {
                    if (is_array($currentValue) && in_array($k, $currentValue)) {
                        $this->logger->debug("discarding $k as it is currently set");
                        unset($options[$k]);
                    }
                }
                if ($mandatory === false || (is_array($currentValue) && sizeof($currentValue) > 0)) {
                    $this->logger->debug("Add none option");
                    $options["_none"] = "None (default)";
                }
                $questionOptions = array_values($options);
                $questionDefaultString = $mandatory ? "" : (sizeof($questionOptions) - 1);

                $this->logger->debug("Asking question", ["question" => $prompt, "options" => $questionOptions, "default" => $questionDefaultString]);

                $answer = $helper->ask(
                    $this->input,
                    $this->output,
                    (new ChoiceQuestion(
                        $prompt,
                        $questionOptions,
                        $questionDefaultString
                    ))->setMultiselect(true)
                );

                $this->logger->debug("Got answer", ["answer" => $answer]);

                $newValue = $currentValue;
                foreach ($answer as $answerLabel) {
                    $key = array_search($answerLabel, $options);
                    if ($key === "_none")
                        continue;
                    $newValue[] = $key;
                    //Check if we have configurations for individual options (e.g. set other config keys)
                    if (array_key_exists("optionsConfiguration", $optionConfig) && is_array($optionConfig["optionsConfiguration"]) && array_key_exists($key, $optionConfig["optionsConfiguration"])) {
                        $this->logger->debug("Have option-specific configurations\n");

                        $specificConfiguration = $optionConfig["optionsConfiguration"][$key];
                        if (is_array($specificConfiguration) && array_key_exists("set", $specificConfiguration)) {
                            foreach ($specificConfiguration["set"] as $effectKey => $effect) {
                                if ($effect === null) {
                                    $this->logger->debug("Unsetting config key $effectKey due to side effect of $key");
                                    $config->unset($effectKey);
                                } else {
                                    $this->logger->debug("Setting config key $effectKey due to side effect of $key");
                                    $config->set($effectKey, $effect);
                                }
                            }
                        }

                    }
                }
                $newValue = array_values(array_unique($newValue));
                if (sizeof($newValue) == 0)
                    $newValue = null;
                break;
            case "boolean":
                $options = [
                    "true" => "Yes",
                    "false" => "No",
                ];
                $prompt = "Please select " . $optionConfig["description"];
                if ($currentValue === null && array_key_exists("default", $optionConfig)) {
                    $currentValue = $optionConfig["default"];

                    $this->logger->debug("Replaced empty value with " . $currentValue);

                }
                if (is_bool($currentValue)) {
                    $prompt .= " (current: " . ($currentValue ? "yes" : "no") . ")";
                }
                $prompt .= ": ";
                if ($default === true) {
                    $options["true"] .= " (default)";
                } else {
                    $options["false"] .= " (default)";
                }

                $questionDefault = $currentValue === null ? null : array_search($currentValue ? "true" : "false", array_keys($options));
                $questionOptions = array_values($options);

                $this->logger->debug("Asking question", ["question" => $prompt, "options" => $questionOptions, "default" => $questionDefault]);

                $answer = $helper->ask(
                    $this->input,
                    $this->output,
                    new ChoiceQuestion(
                        $prompt,
                        $questionOptions,
                        $questionDefault
                    )
                );
                $answerKey = array_keys($options)[array_search($answer, $questionOptions)];
                $newValue = ($answerKey === "true");
                $this->logger->debug("Setting value: " . ($newValue ? "true" : "false"));
                break;
            default:
                throw new Exception("Type undefined");
        }
        if ($newValue !== null) {
            $config->set($path, $newValue);
        } else if ($config->has($path)) {
            $config->unset($path);
        }
    }

    public function initialize($fileName): void {
        $this->persist($fileName, new Config());
    }

    public function persist(string $fileName, Config $config): void {
        $configData = $config->getAll();
        $payload = Yaml::dump($configData) . "\n";
        $this->logger->notice("Persisting configuration", ["config" => $configData, "path" => $fileName]);
        if (@file_put_contents($fileName, $payload) === false)
            throw new Exception("Failed to persist config to " . $fileName);
    }

    public function load(string $fileName): Config {
        $this->logger->notice("Loading configuration", ["path" => $fileName]);
        if (!is_file($fileName))
            throw new Exception($fileName . " does not exist here.");
        $config = Yaml::parseFile($fileName);
        if (!is_array($config))
            throw new Exception("Configuration file broken");
        $ret = new Config(array_replace_recursive((new Config())->getAll(), $config));
        $this->logger->notice("Loaded configuration", ["path" => $fileName, "config" => $ret->getAll()]);
        return $ret;
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