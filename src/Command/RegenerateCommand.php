<?php

namespace App\Command;

use App\Service\ConfigFileService;
use App\Service\FileGeneratorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RegenerateCommand extends Command {
    protected static $defaultName = "quickstart:regenerate";
    /**
     * @var ConfigFileService
     */
    private $configFileService = null;
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $params;
    /**
     * @var FileGeneratorService
     */
    private FileGeneratorService $fileGeneratorService;

    public function __construct(ConfigFileService $configFileService, FileGeneratorService $fileGeneratorService, ParameterBagInterface $params, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
        $this->fileGeneratorService = $fileGeneratorService;
        $this->params = $params;
    }

    protected function configure() {
        $this
            ->setDescription("Regenerates all files created by Quickstart")
            ->setHelp("This command creates or updates all files under the control of Quickstart.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        foreach ($this->params->get("app.templateMappings") as $sourcePath => $templateConfig) {
            $output->writeln("Writing $sourcePath to " . $templateConfig["target"]);
            $this->fileGeneratorService->writeFile($sourcePath, $templateConfig, $config, $cwd);
        }

        $this->configFileService->persist($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE, $config);
        return self::SUCCESS;
    }
}
