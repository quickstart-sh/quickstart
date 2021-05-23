<?php

namespace App\Interfaces;

use App\Entity\Config;

/**
 * Interface for config file ingesters
 * @package App\Interfaces
 */
interface IngesterInterface {
    /**
     * Ingest a file (or OS configuration) into the project configuration
     * @param Config $config
     */
    public function ingest(Config $config, string $baseDir): void;
}