<?php

namespace App\Service;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function ingest(Config $config, string $baseDir, InputInterface $input, OutputInterface $output) {
        foreach ($this->ingesters as $ingester) {
            if (method_exists($ingester, "setInput"))
                $ingester->setInput($input);
            if (method_exists($ingester, "setOutput"))
                $ingester->setOutput($output);
            $ingester->ingest($config, $baseDir);
        }
    }
}