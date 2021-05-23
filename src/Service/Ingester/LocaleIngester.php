<?php

namespace App\Service\Ingester;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;
use Psr\Log\LoggerInterface;

/**
 * Class LocaleIngester
 *
 * This ingester tries to determine the current OS locale.
 * @package App\Service\Ingester
 */
class LocaleIngester implements IngesterInterface {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function ingest(Config $config, string $baseDir): void {
        $this->logger->notice("Attempting to load locale information from OS");
        $locale = "";
        switch (php_uname("s")) {
            case "Linux":
            case "Darwin":
                // Fortunately, OS X adheres to some sort of standard for once
                // Reference for priority: https://www.gnu.org/software/gettext/manual/html_node/Locale-Environment-Variables.html
                $language = getenv("LANGUAGE");
                if ($language !== "" && $language !== false) {
                    // LANGUAGE may contain multiple values, with the most specific being the first one
                    // See https://www.gnu.org/software/gettext/manual/html_node/The-LANGUAGE-variable.html
                    $languages = explode(":", $language);
                    $locale = $language[0];
                    $this->logger->debug("Set locale to $locale from LANGUAGE environment variable");
                } else {
                    $this->logger->debug("Failed to determine value of LANGUAGE environment variable");
                }
                foreach (["LC_ALL", "LC_MESSAGES", "LANG"] as $envVariable) {
                    $value = getenv($envVariable);
                    if ($value !== "" && $value !== false) {
                        $locale = $value;
                        $this->logger->debug("Set locale to $locale from $envVariable environment variable");
                    } else {
                        $this->logger->debug("Failed to determine value of $envVariable environment variable");
                    }
                }
                break;
            default:
                $this->logger->warning("Unsupported OS family " . PHP_OS_FAMILY . ", you will need to set the timezone yourself.");
                // Windows will probably need some Powershell magic? See https://docs.microsoft.com/en-us/powershell/module/international/get-winsystemlocale
                // *BSD should fall under the *nix umbrella above... unfortunately I have no idea what PHP_OS_FAMILY will be on these
                return;
        }
        if ($locale === "") {
            $this->logger->error("Unable to determine current locale from OS, you will need to set the locale yourself.");
        } else {
            $this->logger->warning("Determined locale from OS: $locale");
            $config->set("os.locale", $locale);
        }
    }
}