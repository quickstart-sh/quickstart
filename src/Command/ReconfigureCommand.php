<?php

namespace App\Command;

use App\Entity\Config;
use App\Service\ConfigFileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ReconfigureCommand extends Command {
    protected static $defaultName = "quickstart:reconfigure";
    /**
     * @var ConfigFileService
     */
    private $configFileService = null;

    public function __construct(ConfigFileService $configFileService, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
    }

    protected function configure() {
        $this
            ->setDescription("Reconfigures Quickstart project")
            ->setHelp("This command updates the " . ConfigFileService::CONFIG_FILE . " configuration file");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        var_dump($config->getAll());

        foreach(Config::getOptions("initial") as $path=>$optionConfig)
            ConfigFileService::ask($config,$path,$optionConfig,$input,$output);

        var_dump($config->getAll());
        $this->configFileService->persist($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE, $config);
        return self::SUCCESS;
    }
}