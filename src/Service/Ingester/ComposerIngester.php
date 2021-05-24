<?php

namespace App\Service\Ingester;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;
use Composer\Semver\Semver;
use JsonPath\JsonObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class ComposerIngester
 *
 * This ingester tries to check for the presence of PHP Composer configuration and packages
 * @package App\Service\Ingester
 */
class ComposerIngester implements IngesterInterface {
    private LoggerInterface $logger;
    private InputInterface $input;
    private OutputInterface $output;
    private ParameterBagInterface $params;

    public function __construct(LoggerInterface $logger, ParameterBagInterface $params) {
        $this->logger = $logger;
        $this->params = $params;
    }

    public function ingest(Config $config, string $baseDir): void {
        $this->logger->info("Attempting to load composer.json");
        $composerJsonPath = $baseDir . DIRECTORY_SEPARATOR . "composer.json";
        if (!is_file($composerJsonPath)) {
            // Second chance: is a composer.json somewhere in a sub-directory?
            $finder = new Finder();
            $finder->in($baseDir)->exclude("vendor")->files()->name("composer.json");
            $candidates = [];
            foreach ($finder as $file)
                $candidates[] = $file;
            if (sizeof($candidates) == 0) {
                $this->logger->debug("composer.json not present, skipping");
            } else if (sizeof($candidates) > 1) {
                $helper = new QuestionHelper();
                $this->output->writeln("Found multiple composer.json files, please select one:");
                $questionOptions = array_map(function (SplFileInfo $element) {
                    return $element->getRelativePathname();
                }, $candidates);
                $questionOptions[] = "None";
                $answer = $helper->ask(
                    $this->input,
                    $this->output,
                    new ChoiceQuestion(
                        "Please select",
                        $questionOptions,
                        sizeof($questionOptions)
                    )
                );
                if ($answer === "None") {
                    $this->logger->warning("composer.json not present / selected, skipping");
                    return;
                }
                $composerJsonPath = $baseDir . DIRECTORY_SEPARATOR . $answer;
            }
        }
        $this->logger->info("Loading composer.json from $composerJsonPath");
        $this->logger->notice("Enabling PHP and Composer");
        $config->set("php.enabled", true);
        $config->set("php.composer.enabled", true);
        $config->set("php.composer.jsonPath", substr(dirname($composerJsonPath), strlen($baseDir)));
        //Now, load the JSON and attempt to distill some extra infos
        try {
            $json = new JsonObject(file_get_contents($composerJsonPath), true);
            //Vendor directory
            $vendorDir = $json->get('$.config.vendor-dir');
            if ($vendorDir !== false && $vendorDir !== null && $vendorDir !== "") {
                $this->logger->info("Setting vendor directory to $vendorDir");
                $config->set("php.composer.vendorDir", $vendorDir);
            }
            //bin directory
            $binDir = $json->get('$.config.bin-dir');
            if ($binDir !== false && $binDir !== null && $binDir !== "") {
                $this->logger->info("Setting bin directory to $vendorDir");
                $config->set("php.composer.binDir", $binDir);
            }
            //minimum PHP version
            $phpVersion = $json->get("$.require.php");
            if ($phpVersion !== false && $phpVersion !== null && $phpVersion !== "") {
                $availableVersions = array_keys($this->params->get("app.tree.initial")["php.version"]["options"]);
                $matchingVersion = "";
                foreach ($availableVersions as $candidateVersion) {
                    $this->logger->debug("Comparing $candidateVersion against $phpVersion");
                    if (Semver::satisfies($candidateVersion, $phpVersion)) {
                        $matchingVersion = $candidateVersion;
                        break;
                    }
                }
                if ($matchingVersion == "") {
                    $this->logger->error("Failed to find a PHP version in the Quickstart repository that satisfies the project requirement $phpVersion");
                } else {
                    $this->logger->notice("Found a matching PHP version: $matchingVersion");
                    $config->set("php.version", $matchingVersion);
                }
            } else {
                $this->logger->error("Unable to determine minimum PHP version from composer.json, you will need to set the version yourself.");
            }
            //Now, attempt to load extensions
            $availableExtensions = array_keys($this->params->get("app.phpExtensions"));
            $prodDependencies = $json->get("$.require");
            if (is_array($prodDependencies)) {
                foreach (array_keys($prodDependencies) as $depName) {
                    if (substr($depName, 0, 4) !== "ext-")
                        continue;
                    $extName = substr($depName, 4);
                    if (in_array($extName, $availableExtensions)) {
                        $this->logger->info("Adding extension $extName to base image");
                        $config->set("php.extensions.base.[]", $extName);
                    } else {
                        $this->logger->error("Failed to find a package in the Quickstart repository for the project requirement $extName");
                    }
                }
            }
            $devDependencies = $json->get("$.require-dev");
            if (is_array($devDependencies)) {
                foreach (array_keys($devDependencies) as $depName) {
                    if (substr($depName, 0, 4) !== "ext-")
                        continue;
                    $extName = substr($depName, 4);
                    if (in_array($extName, $availableExtensions)) {
                        $this->logger->info("Adding extension $extName to tooling image");
                        $config->set("php.extensions.tooling.[]", $extName);
                    } else {
                        $this->logger->error("Failed to find a package in the Quickstart repository for the project tooling requirement $extName");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed ingesting composer.json", ["exception" => $e]);
        }
    }

    /**
     * Composer ingestion must happen after NodeJS to allow for projects with NodeJS frontends
     * @return int
     */
    public static function getDefaultPriority(): int {
        return 5;
    }

    /**
     * @param InputInterface $input
     */
    public function setInput(InputInterface $input): void {
        $this->input = $input;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void {
        $this->output = $output;
    }

}