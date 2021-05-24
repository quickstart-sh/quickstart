<?php

namespace App\Service\Ingester;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;
use JsonPath\JsonObject;
use Psr\Log\LoggerInterface;

/**
 * Class SymfonyIngester
 *
 * This ingester tries to check for the presence of Symfony components
 * @package App\Service\Ingester
 */
class SymfonyIngester implements IngesterInterface {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function ingest(Config $config, string $baseDir): void {
        $this->logger->info("Attempting to load Symfony component configuration");
        if ($config->get("php.composer.enabled") !== true) {
            $this->logger->debug("Composer not enabled during ingest, skipping");
            return;
        }
        $composerJsonPath = $baseDir . DIRECTORY_SEPARATOR . $config->get("php.composer.jsonPath") . DIRECTORY_SEPARATOR . "composer.json";
        $this->logger->info("Loading composer.json from $composerJsonPath");
        try {
            $json = new JsonObject(file_get_contents($composerJsonPath), true);
            $prodDependencies = $json->get("$.require");
            if (is_array($prodDependencies)) {
                foreach (array_keys($prodDependencies) as $depName) {
                    if ($depName === "symfony/flex") {
                        $this->logger->info("Detected Symfony Flex package, setting project type to symfony");
                        $config->set("project.type", "symfony");
                    } else if ($depName === "symfony/webpack-encore-bundle") {
                        $this->logger->info("Detected Symfony Webpack/Encore package, setting project type to Symfony Web");
                        //Unfortunately there is nothing *specifically* indicating a Web project... except this one clear-cut package.
                        $config->set("project.type", "symfony");
                        $config->set("symfony.type", "web");
                        $config->set("symfony.modules.[]","symfony/webpack-encore-bundle");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed ingesting composer.json", ["exception" => $e]);
        }
    }

    /**
     * Symfony ingestion must happen after Composer to allow for detection of composer.json
     * @return int
     */
    public static function getDefaultPriority(): int {
        return 4;
    }
}