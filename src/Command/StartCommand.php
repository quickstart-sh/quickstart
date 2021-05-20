<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StartCommand extends Command {
    protected static $defaultName = "quickstart:start";
    /**
     * @var ConfigFileService
     */
    private ConfigFileService $configFileService;

    public function __construct(ConfigFileService $configFileService, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
    }

    protected function configure() {
        $this
            ->setDescription("Starts all containers required by the project")
            ->setHelp("This command creates and starts the containers.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        $command = [
            "docker-compose",
            "-f",
            "docker/docker-compose.yml",
            "-p",
            $config->get("project.name"),
            "up",
            "-d",
        ];

        $process = new Process($command);
        $process->mustRun(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return self::SUCCESS;
    }
}
