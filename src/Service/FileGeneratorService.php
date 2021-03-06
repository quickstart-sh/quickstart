<?php

namespace App\Service;

use App\Entity\Config;
use Twig\Environment;

class FileGeneratorService {

    /**
     * Development aid - turn on echo on all major code paths
     */
    private const DEBUG_ME = false;
    /**
     * @var Environment
     */
    private Environment $twig;

    /**
     * FileGeneratorService constructor.
     * @param Environment $twig
     * @codeCoverageIgnore
     */
    public function __construct(Environment $twig) {
        $this->twig = $twig;
    }

    /**
     * Render a config file to its target using Twig
     * @param string $sourcePath
     * @param array $templateConfig
     * @param Config $config
     * @param string $baseDir
     */
    public function writeFile(string $sourcePath, array $templateConfig, Config $config, string $baseDir) {
        $targetPath = $baseDir . DIRECTORY_SEPARATOR . $templateConfig["target"];
        //Check if we are allowed to write the file
        if (array_key_exists("if", $templateConfig) && ConditionEvaluatorService::evaluate($templateConfig["if"], $config) === false) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("File $sourcePath, passing as condition " . $templateConfig["if"] . " returned false\n");
            //@codeCoverageIgnoreEnd
            if (is_file($targetPath)) {
                //@codeCoverageIgnoreStart
                if (self::DEBUG_ME) echo("File $sourcePath, removing existing file\n");
                //@codeCoverageIgnoreEnd
                if (@unlink($targetPath) === false) {
                    throw new \RuntimeException("Failed to delete $targetPath");
                }
            }
            return;
        }
        //Check and create directories if needed
        $parentDirectory = dirname($targetPath);
        if (!file_exists($parentDirectory)) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("File $sourcePath, creating parent directory $parentDirectory\n");
            //@codeCoverageIgnoreEnd
            if (@mkdir($parentDirectory, 0775, true) === false) {
                throw new \RuntimeException("Failed to create $parentDirectory");
            }
        }

        $content = $this->twig->render($sourcePath, [
            "config" => $config,
        ]);

        $content = preg_replace('/[\n]{3,}/', "\n", $content);

        if (@file_put_contents($targetPath, $content) === false)
            throw new \RuntimeException("Failed to persist " . $targetPath);

        if (array_key_exists("chmod", $templateConfig)) {
            //@codeCoverageIgnoreStart
            if (self::DEBUG_ME) echo("File $sourcePath, setting chmod to " . decoct($templateConfig["chmod"]) . "\n");
            //@codeCoverageIgnoreEnd
            //Note: vfsStreamAbstractContent's chmod() can't be provoked to error out...
            if (@chmod($targetPath, $templateConfig["chmod"]) === false) {
                //@codeCoverageIgnoreStart
                throw new \RuntimeException("Failed to chmod $targetPath");
                //@codeCoverageIgnoreEnd
            }

        }
    }
}
