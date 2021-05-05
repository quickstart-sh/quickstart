<?php

namespace App\Command;

use App\Service\ConfigFileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReconfigureCommand extends Command {
    protected static $defaultName = "quickstart:reconfigure";
    /**
     * @var ConfigFileService
     */
    private $configFileService = null;
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $params;

    public function __construct(ConfigFileService $configFileService, ParameterBagInterface $params, string $name = null) {
        parent::__construct($name);
        $this->configFileService = $configFileService;
        $this->params = $params;
    }

    protected function configure() {
        $this
            ->setDescription("Reconfigures Quickstart project")
            ->setHelp("This command updates the " . ConfigFileService::CONFIG_FILE . " configuration file")
            ->addArgument("stage", InputArgument::OPTIONAL, "Stage to start at", "initial");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $cwd = getcwd();
        $output->writeln("Attempting to load " . $cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);
        $config = $this->configFileService->load($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE);

        $stages = [
            "initial",
            "vcs",
            "ci",
            "cd",
        ];
        $startStage = $input->getArgument("stage");
        if (!in_array($startStage, $stages))
            throw new \InvalidArgumentException("Invalid stage");

        $this->configFileService->setInput($input);
        $this->configFileService->setOutput($output);
        for ($i = array_search($startStage, $stages); $i < sizeof($stages); $i++) {
            $stage = $stages[$i];
            //$output->writeln("At stage $stage");
            foreach ($this->params->get("app.tree.$stage") as $path => $optionConfig) {
                //$output->writeln("Asking $path");
                $this->configFileService->ask($config, $path, $optionConfig);
            }
        }

        $this->configFileService->persist($cwd . DIRECTORY_SEPARATOR . ConfigFileService::CONFIG_FILE, $config);
        return self::SUCCESS;
    }
}