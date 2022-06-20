<?php

namespace App\Service;

use App\Entity\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

class InstallService {

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $params;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var OutputInterface
     */
    private OutputInterface $output;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger) {
        $this->params = $params;
        $this->logger = $logger;
    }

    /**
     * Set an output to dump the progress of the installation command to
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * Execute a command
     * @param array $command
     * @param Config $config
     */
    public function executeCommand(array $command, Config $config, bool $ignoreErrors) {
        $this->logger->notice("Executing " . implode(" ", $command));
        foreach ($command as $index => $arg) {
            //Replace dynamic parameters (e.g. project name)
            $command[$index] = preg_replace_callback('/#(.*?)#/m', function ($matches) use ($config) {
                $replacement = $config->get($matches[1]);
                $this->logger->debug("Replacing " . $matches[0] . " with " . $replacement);
                return $replacement;
            }, $arg);
            //Replace array parameters (e.g. a list of Composer packages)
            if(preg_match("/^§(.*)§$/", $command[$index], $matches)===1) {
                $replacement = $config->get($matches[1]);
                $this->logger->debug("Replacing " . $matches[0] . " with [" . implode(",",$replacement)."]");
                $command[$index]=$replacement;
            }
        }
        //Flatten the parameter array, as some config parameters may return arrays
        $finalCommand = [];
        foreach ($command as $arg) {
            if (is_array($arg))
                $finalCommand = array_merge($finalCommand, $arg);
            else
                $finalCommand[] = $arg;
        }
        $this->logger->notice("Final command: " . implode(" ", $finalCommand));

        $process = new Process($finalCommand);
        $process->setTimeout(null);
        $process->mustRun(function ($type, $buffer) {
            if ($this->output instanceof OutputInterface)
                $this->output->write($buffer);
        });
    }

    /**
     * Run the installation commands as wanted by the project
     * @param Config $config
     */
    public function run(Config $config) {
        $commands = $this->params->get("app.installScripts." . $config->get("project.type"));
        foreach ($commands as $command) {
            if (array_key_exists("if", $command) && ConditionEvaluatorService::evaluate($command["if"], $config) === false) {
                $this->logger->debug("Skipping " . implode(" ", $command["command"]) . " due to failed condition " . $command["if"]);
                continue;
            }
            if (array_key_exists("inShell", $command) && $command["inShell"] === true) {
                $this->logger->debug("Wrapping command in sh -c");
                $command["command"] = array_merge([
                    "sh",
                    "-c",
                    substr(escapeshellarg(implode(" ", $command["command"])), 1, -1),
                ]);
            }
            //Execute this command inside the primary application container?
            if (array_key_exists("inContainer", $command) && $command["inContainer"] === true) {
                $this->logger->debug("Prepending docker-compose exec to command");
                $command["command"] = array_merge([
                    "docker-compose",
                    "-f", "docker/docker-compose.yml",
                    "-p", $config->get("project.name"),
                    "exec",
                    "-u", "www-data",
                    "-T",
                    "app",
                ], $command["command"]);
            }

            $this->executeCommand($command["command"], $config, (array_key_exists("ignoreErrors", $command) && $command["ignoreErrors"] === true));
        }
    }
}