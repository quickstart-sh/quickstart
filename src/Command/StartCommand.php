<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'quickstart:start'
)]
class StartCommand extends Command implements LoggerAwareInterface {
    use LoggerAwareTrait;

    public function __construct(private readonly ConfigFileService $configFileService, string $name = null) {
        parent::__construct($name);
    }

    protected function configure(): void {
        $this
            ->setDescription("Starts all containers required by the project")
            ->setHelp("This command creates and starts the containers.")
            ->addOption("rebuild", "r", InputOption::VALUE_NONE, "Rebuild application image");
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
            "up",
            "-d",
        ];

        if ($input->getOption("rebuild"))
            $command[] = "--build";

        $this->logger->notice("Launching process", ["command" => $command]);

        $process = new Process($command);
        $process->setTimeout(null);
        $process->mustRun(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return self::SUCCESS;
    }
}
