<?php

namespace App\Command;

use App\Service\ConfigFileService;
use App\Service\IngesterService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class IngestConfigFilesCommand extends Command {
    protected static $defaultName = "quickstart:ingest-config-files";
    /**
     * @var ConfigFileService
     */
    private $configFileService = null;
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $params;
    private IngesterService $ingesterService;

    public function __construct(ConfigFileService $configFileService, ParameterBagInterface $params, IngesterService $ingesterService, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
        $this->params = $params;
        $this->ingesterService = $ingesterService;
    }

    protected function configure() {
        $this
            ->setDescription("Ingest existing third-party configuration into the Quickstart project")
            ->setHelp("This command updates the " . ConfigFileService::CONFIG_FILE . " configuration file based on existing configuration files");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        $this->ingesterService->ingest($config, $cwd);

        $this->configFileService->persist($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE, $config);
        return self::SUCCESS;
    }
}