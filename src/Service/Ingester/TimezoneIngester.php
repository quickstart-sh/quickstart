<?php

namespace App\Service\Ingester;

use App\Entity\Config;
use App\Interfaces\IngesterInterface;
use Psr\Log\LoggerInterface;

class TimezoneIngester implements IngesterInterface {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function ingest(Config $config, string $baseDir): void {
        $this->logger->notice("Attempting to load timezone information from OS");
        $tz = "";
        switch (php_uname("s")) {
            case "Linux":
                //Three different ways on Linux: /etc/localtime, /etc/timezone, TZ env
                if (is_link("/etc/localtime")) {
                    // According to https://www.freedesktop.org/software/systemd/man/localtime.html,
                    // /etc/localtime should be a symlink to the zone under /usr/share/zoneinfo
                    $tzpath = readlink("/etc/localtime");
                    $basepath = "/usr/share/zoneinfo/";
                    if (substr($tzpath, 0, strlen($basepath)) !== $basepath) {
                        $this->logger->warning("Your OS has /etc/localtime pointing to something other than $basepath. Please fix your setup according to 'man localtime'.");
                    } else {
                        $tz = substr($tzpath, strlen($basepath));
                        $this->logger->debug("Set timezone to $tz from /etc/localtime symlink");
                    }
                } else if (is_file("/etc/localtime")) {
                    $this->logger->warning("Your OS has /etc/localtime as a file instead of a symlink. Please fix your setup according to 'man localtime'.");
                } else {
                    $this->logger->warning("Your OS does not have /etc/localtime. Please fix your setup according to 'man localtime'.");
                }
                if (is_file("/etc/timezone")) {
                    // /etc/timezone contains the raw zone name in Debian-based distributions, see https://wiki.debian.org/TimeZoneChanges
                    $debianTimezone = trim(file_get_contents("/etc/timezone"));
                    if ($debianTimezone === "") {
                        $this->logger->warning("Your /etc/timezone file is empty. Please fix your setup according to https://wiki.debian.org/TimeZoneChanges");
                    } else if ($tz !== "" && $tz != $debianTimezone) {
                        $this->logger->warning("The content of /etc/timezone is $debianTimezone, your /etc/localtime indicates $tz. Please fix your setup according to https://wiki.debian.org/TimeZoneChanges");
                    } else if ($tz !== "" && $tz === $debianTimezone) {
                        $this->logger->debug("/etc/timezone and /etc/localtime are identical ($tz)");
                    } else if ($tz === "") {
                        $this->logger->debug("Set timezone to $tz from /etc/timezone content");
                    }
                }
                $envTimezone = getenv("TZ");
                if ($envTimezone === false || $envTimezone === "") {
                    $this->logger->debug("Failed to determine value of TZ environment variable");
                } else if ($tz !== "") {
                    $this->logger->warning("TZ environment variable $envTimezone differs from system-determined $tz");
                    $tz = $envTimezone;
                } else {
                    $this->logger->debug("Set timezone to $tz from TZ environment variable");
                    $tz = $envTimezone;
                }
                break;
            case "Darwin":
                if (is_link("/etc/localtime")) {
                    // According to 'man tzset',
                    // /etc/localtime should be a symlink to the zone under /var/db/timezone/zoneinfo
                    $tzpath = readlink("/etc/localtime");
                    $basepath = "/var/db/timezone/zoneinfo/";
                    if (substr($tzpath, 0, strlen($basepath)) !== $basepath) {
                        $this->logger->warning("Your OS has /etc/localtime pointing to something other than $basepath. Please fix your setup according to 'man tzset'.");
                    } else {
                        $tz = substr($tzpath, strlen($basepath));
                        $this->logger->debug("Set timezone to $tz from /etc/localtime symlink");
                    }
                } else if (is_file("/etc/localtime")) {
                    $this->logger->warning("Your OS has /etc/localtime as a file instead of a symlink. Please fix your setup according to 'man tzset'.");
                } else {
                    $this->logger->warning("Your OS does not have /etc/localtime. Please fix your setup according to 'man tzset'.");
                }
                $envTimezone = getenv("TZ");
                if ($envTimezone === false || $envTimezone === "") {
                    $this->logger->debug("Failed to determine value of TZ environment variable");
                } else if ($tz !== "") {
                    $this->logger->warning("TZ environment variable $envTimezone differs from system-determined $tz");
                    $tz = $envTimezone;
                } else {
                    $this->logger->debug("Set timezone to $tz from TZ environment variable");
                    $tz = $envTimezone;
                }
                break;
            default:
                $this->logger->warning("Unsupported OS family " . PHP_OS_FAMILY . ", you will need to set the timezone yourself.");
                //Windows: maybe include a binary .net helper? See https://github.com/dotnet/runtime/issues/18644
                //*BSD - these should be using /etc/localtime, but copy instead of symlink sometimes...
                return;
        }
        if ($tz === "") {
            $this->logger->error("Unable to determine current timezone from OS, you will need to set the timezone yourself.");
        } else {
            $this->logger->warning("Determined timezone from OS: $tz");
            $config->set("os.timezone", $tz);
        }
    }
}