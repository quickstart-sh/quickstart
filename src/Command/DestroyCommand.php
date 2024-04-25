<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
#[AsCommand(
    name: 'quickstart:destroy'
)]
class DestroyCommand extends Command {
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
            ->setDescription("Destroy all containers required by the project")
            ->setHelp("This command destroys all containers and their data.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        $command = [
            "docker",
            "compose",
            "-f",
            "docker-compose.yml",
            "-p",
            $config->get("project.name"),
            "down",
        ];

        $process = new Process($command);
        $process->setTimeout(null);
        $process->mustRun(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return self::SUCCESS;
    }
}
