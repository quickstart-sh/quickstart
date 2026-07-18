<?php

namespace App\Command;

use App\Service\ConfigFileService;
use App\Service\FileGeneratorService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'quickstart:regenerate',
    description: "Regenerates all files created by Quickstart",
    help: "This command creates or updates all files under the control of Quickstart.",
)]
class RegenerateCommand extends Command implements LoggerAwareInterface {
    use LoggerAwareTrait;

    public function __construct(private readonly ConfigFileService $configFileService, private readonly FileGeneratorService $fileGeneratorService, private readonly ParameterBagInterface $params, string $name = null) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
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
