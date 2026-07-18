<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'quickstart:init',
    description: "Initializes a new Quickstart project",
    help: "This command creates and populates the " . ConfigFileService::CONFIG_FILE . " configuration file",
)]
class InitCommand extends Command implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * InitCommand constructor.
     * @param ConfigFileService $configFileService
     * @param string|null $name
     * @codeCoverageIgnore
     */
    public function __construct(private readonly ConfigFileService $configFileService, string $name = null) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $cwd = getcwd();
        if (is_file($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE)) {
            throw new Exception(ConfigFileService::CONFIG_FILE . " already exists here. Use " . (new ReflectionClass(ReconfigureCommand::class))->getAttributes(AsCommand::class)[0]->getArguments()["name"] . " to re-configure.");
        }
        $this->logger->debug("Initializing config file", ["path" => $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE]);
        $this->configFileService->initialize($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        return self::SUCCESS;
    }
}