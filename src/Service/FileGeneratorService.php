<?php

namespace App\Service;

use App\Entity\Config;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FileGeneratorService implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * @var Environment
     */
    private Environment $twig;
    /**
     * @var ConditionEvaluatorService
     */
    private ConditionEvaluatorService $conditionEvaluatorService;

    /**
     * FileGeneratorService constructor.
     * @param Environment $twig
     * @param ConditionEvaluatorService $conditionEvaluatorService
     */
    public function __construct(Environment $twig, ConditionEvaluatorService $conditionEvaluatorService) {
        $this->twig = $twig;
        $this->conditionEvaluatorService = $conditionEvaluatorService;
    }

    /**
     * Render a config file to its target using Twig
     * @param string $sourcePath
     * @param array $templateConfig
     * @param Config $config
     * @param string $baseDir
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function writeFile(string $sourcePath, array $templateConfig, Config $config, string $baseDir): void {
        $targetPath = $baseDir . DIRECTORY_SEPARATOR . $templateConfig["target"];
        //Check if we are allowed to write the file
        if (array_key_exists("if", $templateConfig) && $this->conditionEvaluatorService->evaluate($templateConfig["if"], $config) === false) {
            $this->logger->debug("File $sourcePath, passing as condition " . $templateConfig["if"] . " returned false\n");
            if (is_file($targetPath)) {
                $this->logger->debug("File $sourcePath, removing existing file\n");
                if (@unlink($targetPath) === false) {
                    throw new RuntimeException("Failed to delete $targetPath");
                }
            }
            return;
        }
        //Check and create directories if needed
        $parentDirectory = dirname($targetPath);
        if (!file_exists($parentDirectory)) {
            $this->logger->debug("File $sourcePath, creating parent directory $parentDirectory\n");
            if (@mkdir($parentDirectory, 0775, true) === false) {
                throw new RuntimeException("Failed to create $parentDirectory");
            }
        }

        $content = $this->twig->render($sourcePath, [
            "config" => $config,
        ]);

        $content = preg_replace('/\n{3,}/', "\n", $content);

        if (@file_put_contents($targetPath, $content) === false)
            throw new RuntimeException("Failed to persist " . $targetPath);

        if (array_key_exists("chmod", $templateConfig)) {
            $this->logger->debug("File $sourcePath, setting chmod to " . decoct($templateConfig["chmod"]) . "\n");
            if (@chmod($targetPath, $templateConfig["chmod"]) === false) {
                //Note: vfsStreamAbstractContent's chmod() can't be provoked to error out, thus exclude from CC metrics
                //@codeCoverageIgnoreStart
                throw new RuntimeException("Failed to chmod $targetPath");
                //@codeCoverageIgnoreEnd
            }
        }
    }
}
