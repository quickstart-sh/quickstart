<?php

namespace App\Service;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;

class IngesterService {
    /**
     * @var IngesterInterface[]
     */
    private array $ingesters;

    public function __construct(iterable $ingesters) {
        $this->ingesters = [];
        foreach ($ingesters as $ingester) {
            $this->ingesters[] = $ingester;
        }
    }

    /**
     * Run all ingesters over the current config
     * @param Config $config
     * @param string $baseDir
     */
    public function ingest(Config $config, string $baseDir) {
        foreach ($this->ingesters as $ingester) {
            $ingester->ingest($config, $baseDir);
        }
    }
}