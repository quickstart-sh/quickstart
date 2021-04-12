<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command {
    protected static $defaultName = "quickstart:init";
    /**
     * @var ConfigFileService
     */
    private $configFileService = null;

    /**
     * InitCommand constructor.
     * @param ConfigFileService $configFileService
     * @param string|null $name
     * @codeCoverageIgnore
     */
    public function __construct(ConfigFileService $configFileService, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function configure() {
        $this
            ->setDescription("Initializes a new Quickstart project")
            ->setHelp("This command creates and populates the " . ConfigFileService::CONFIG_FILE . " configuration file");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $cwd = getcwd();
        if (is_file($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE))
            throw new \Exception(ConfigFileService::CONFIG_FILE . " already exists here. Use " . ReconfigureCommand::getDefaultName() . " to re-configure.");
        $this->configFileService->initialize($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $command = $this->getApplication()->find(ReconfigureCommand::getDefaultName());
        return $command->execute($input, $output);
    }
}