<?php

namespace App\Command;

use App\Service\ConfigFileService;
use App\Service\InstallService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command {
    protected static $defaultName = "quickstart:install";
    /**
     * @var ConfigFileService
     */
    private ConfigFileService $configFileService;

    /**
     * @var InstallService
     */
    private InstallService $installService;

    public function __construct(ConfigFileService $configFileService, InstallService $installService, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
        $this->installService = $installService;
    }

    protected function configure() {
        $this
            ->setDescription("Creates the project by installing the project scaffold")
            ->setHelp("This command creates the project and configures as much as possible.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        if ($config->get("project.installed") === true)
            throw new \InvalidArgumentException("This project is already installed.");

        $this->installService->setOutput($output);
        $this->installService->run($config);

        $config->set("project.installed", true);

        $this->configFileService->persist($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE, $config);
        return self::SUCCESS;
    }
}
