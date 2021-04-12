<?php

namespace App\Entity;
class Config {
    /**
     * Default version of new Config objects.
     *
     * TODO: implement upgrade strategy...
     */
    public const DEFAULT_VERSION = 1;

    /**
     * Get the configuration options for the user to fill out.
     *
     * TODO move this into a yml file? Need to find a way to deal with getcwd() call
     * @param null $group
     * @return array|array[]
     * @codeCoverageIgnore
     */
    public static function getOptions($group = null): array {
        $options = [
            "name" => [
                "type" => "string",
                "description" => "the name of the project",
                "defaultDescription" => "name of current directory",
                "mandatory" => true,
                "default" => getcwd(),
                "group" => "initial",
            ],
            "os.name" => [
                "type" => "select_single",
                "description" => "the base OS for the Docker images",
                "options" => [
                    "ubuntu" => "Ubuntu",
                    "debian" => "Debian",
                    "alpine" => "Alpine"
                ],
                "default" => "ubuntu",
                "group" => "initial",

            ],
            "os.ubuntu_version" => [
                "type" => "select_single",
                "if" => "#os.name# === 'ubuntu'",
                "pathOverride" => "version",
                "description" => "the Ubuntu version for the Docker images",
                "options" => [
                    "18.04" => "18.04 LTS",
                    "20.04" => "20.04 LTS",
                ],
                "default" => "20.04",
                "group" => "initial",

            ],
            "os.debian_version" => [
                "type" => "select_single",
                "if" => "#os.name# === 'debian'",
                "pathOverride" => "version",
                "description" => "the Debian version for the Docker images",
                "options" => [
                    "buster" => "buster / stable",
                    "bullseye" => "bullseye / testing"
                ],
                "default" => "buster",
                "group" => "initial",

            ],
            "os.alpine_version" => [
                "type" => "select_single",
                "if" => "#os.name# === 'alpine'",
                "pathOverride" => "version",
                "description" => "the Alpine version for the Docker images",
                "options" => [
                    "3.12" => "3.12 (2020-05)",
                    "3.13" => "3.13 (2021-01)"
                ],
                "default" => "3.13",
                "group" => "initial",

            ],
            "type" => [
                "type" => "select_single",
                "description" => "the project type",
                "options" => [
                    "symfony" => "Symfony web/console app",
                    "drupal" => "Drupal site",
                    "wordpress" => "Wordpress site",

                    "reactjs" => "ReactJS app",
                    "gatsby" => "Gatsby app",
                    "generic_nodejs_web" => "Other NodeJS web app (nuxt, jekyll, ...)",

                    "generic_nodejs_server" => "NodeJS server app",

                    "generic_static_web" => "Generic static Web content",
                    "generic_php_web" => "Generic PHP Web content",
                    "generic_php_cli" => "Generic PHP CLI application",
                ],
                "optionsConfiguration" => [
                    "symfony" => [
                        "set" => [
                            "php.enabled" => true,
                            "externalServices.enabled" => true,
                        ]
                    ],
                    "drupal" => [
                        "set" => [
                            "php.enabled" => true,
                            "webserver.enabled" => true,
                            "externalServices.enabled" => true,
                        ]
                    ],
                    "wordpress" => [
                        "set" => [
                            "php.enabled" => true,
                            "webserver.enabled" => true,
                            "externalServices.enabled" => true,
                        ]
                    ],
                    "generic_php_web" => [
                        "set" => [
                            "php.enabled" => true,
                            "webserver.enabled" => true,
                            "externalServices.enabled" => true,
                        ]
                    ],
                    "generic_php_cli" => [
                        "set" => [
                            "php.enabled" => true,
                            "webserver.enabled" => true,
                            "externalServices.enabled" => true,
                        ]
                    ],
                    "reactjs" => [
                        "set" => [
                            "nodejs.enabled" => true,
                            "webserver.enabled" => true,
                        ]
                    ],
                    "gatsby" => [
                        "set" => [
                            "nodejs.enabled" => true,
                            "webserver.enabled" => true,
                        ]
                    ],
                    "generic_nodejs_web" => [
                        "set" => [
                            "nodejs.enabled" => true,
                            "webserver.enabled" => true,
                        ]
                    ],
                    "generic_nodejs_server" => [
                        "set" => [
                            "nodejs.enabled" => true,
                            "externalServices.enabled" => true,
                        ],
                    ],
                    "generic_static_web" => [
                        "set" => [
                            "webserver.enabled" => true,
                        ]
                    ],
                ],
                "group" => "initial",
                "final" => true,
            ],
            "webserver.software" => [
                "type" => "select_single",
                "description" => "the web server",
                "if" => "#webserver.enabled# === true",
                "options" => [
                    "apache" => "Apache",
                    "nginx" => "Nginx",
                    "lighttpd" => "lighttpd",
                ],
                "default" => "apache",
                "group" => "initial",

            ],
            "php.webserverIntegrationType" => [
                "type" => "select_single",
                "description" => "the PHP integration",
                "if" => "#webserver.enabled# === true && #php.enabled# === true",
                "options" => [
                    "mod_php" => "mod_php",
                    "php_fpm" => "PHP-FPM",
                ],
                "default" => [
                    "mod_php" => "#webserver.software# === 'apache'",
                    "php_fpm" => "#webserver.software# !== 'apache'",
                ],
                "optionsConfiguration" => [
                    "mod_php" => [
                        "if" => "#webserver.software# === 'apache'",
                    ]
                ],
                "group" => "initial",

            ],
            "externalServices.types" => [
                "type" => "select_multi",
                "description" => "additional services",
                "if" => "#externalServices.enabled# === true",
                "options" => [
                    "mariadb" => "MariaDB",
                    "mysql" => "MySQL",
                    "postgres" => "PostgreSQL",
                    "mongodb" => "MongoDB",
                    "sqlite" => "SQLite",
                    "mail" => "Mailcatcher (dummy SMTP server)",
                    "redis" => "Redis Cache",
                    "elasticsearch" => "ElasticSearch",
                    "memcached" => "Memcached",
                    "ldap" => "OpenLDAP + phpldapadmin",
                    "solr" => "Solr"
                ],
                "group" => "initial",

            ],
            "php.version" => [
                "type" => "select_single",
                "description" => "the PHP version",
                "if" => "#php.enabled# === true && in_array(#os.name#,['ubuntu'])",
                "options" => [
                    "php7.3" => "PHP 7.3",
                    "php7.4" => "PHP 7.4",
                    "php8.0" => "PHP 8.0",
                ],
                "default" => "php7.4",
                "group" => "initial",

            ],
            "symfony.modules" => [
                "type" => "select_multi",
                "description" => "Symfony bundles and packs",
                "if" => "#type# == 'symfony'",
                "options" => [
                    "symfony/console" => "Console",
                    "symfony/ldap" => "LDAP",
                    "symfony/form" => "Forms",
                    "symfony/twig-pack" => "Twig",
                    "symfony/maker-bundle" => "Maker bundle",
                    "doctrine/doctrine-bundle" => "Doctrine Base",
                    "orm-fixtures" => "Doctrine Fixtures",
                    "doctrine/doctrine-migrations-bundle" => "Doctrine Migrations",
                    "symfony/monolog-bundle" => "Monolog Logger",
                    "symfony/apache-pack" => "Apache integration",
                    "symfony/cache" => "Cache",
                    "symfony/profiler-pack" => "Profiler pack",
                    "symfony/mailer" => "Mailer",
                    "symfony/phpunit-bridge" => "PHPUnit bridge",
                    "symfony/security-bundle" => "Security",
                    "symfony/serializer" => "Serializer",
                    "symfony/translation" => "Translation",
                    "symfony/webpack-encore-bundle" => "Webpack / Encore bundle",
                ],
                "group" => "initial",

            ],
            "php.modules" => [
                "type" => "select_multi",
                "description" => "the PHP modules",
                "if" => "#php.enabled# === true",
                "options" => [
                    /*
                     * Commented (and a boatload of others) will always (enforced during setup!) be available.
                     * Database modules automatically with the database service
                     */
                    "amqp" => "AMQP",
                    "apcu" => "APCu",
                    "gmagick" => "GraphicsMagick",
                    "gnupg" => "GnuPG",
                    "imagick" => "php-imagick",
                    "memcache" => "memcache",
                    "memcached" => "memcache (using libmemcached)",
                    "ssh2" => "SSH",
                    "uploadprogress" => "upload progress",
                    "uuid" => "UUID",
                    "xdebug" => "XDebug",
                    "grpc" => "gRPC",
                    "imap" => "IMAP",
                    "inotify" => "inotify",
                    "intl" => "Internationalization",
                    //"json"=>"JSON"
                    "ldap" => "ldap", //Offer this one as an option, will be auto-enabled when OpenLDAP is selected above
                    "mbstring" => "mbstring",
                    "mcrypt" => "libmcrypt",
                    "opcache" => "opcache",
                    "protobuf" => "protobuf",
                    "radius" => "RADIUS",
                    //"curl" => "cURL",
                    "redis" => "Redis",
                    //"readline"=>"Readline"
                    "snmp" => "SNMP",
                    "soap" => "XML-SOAP",
                    "xml" => "XML",
                    //"yaml"=>"YAML"
                    //"zip"=>"ZIP"
                    "gd" => "GD graphics library",
                    "gettext" => "GNU gettext",
                ],
                "group" => "initial",

            ],

            "nodejs.version" => [
                "type" => "select_single",
                "description" => "the NodeJS version",
                "if" => "#nodejs.enabled# === true",
                "options" => [
                    "dubnium" => "v10 LTS (dubnium)",
                    "erbium" => "v12 LTS (erbium)",
                    "fermium" => "v14 LTS (fermium)",
                ],
                "default" => "fermium",
                "group" => "initial",

            ],
        ];
        if ($group === null)
            return $options;
        $ret = [];
        foreach ($options as $path => $option) {
            if ($option["group"] === $group)
                $ret[$path] = $option;
        }
        return $ret;
    }

    /**
     * @var array Configuration data
     */
    private $config = [];

    /**
     * Config constructor.
     * @param array $config
     */
    public function __construct(array $config = []) {
        $this->setAll($config);
    }

    /**
     * Set a config key to value
     *
     * path follows loosely the jq syntax. A dot descends into the array at the specified key. [] can be given
     * at the end, this pushes the value into an array at the parent path.
     *
     * Missing array keys are created on demand.
     * @param string $path
     * @param $value
     * @throws \Exception
     */
    public function set(string $path, $value) {
        //echo "\n";
        //echo "setting $path to ".(is_array($value)?print_r($value,true):$value)."\n";
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!is_array($current)) {
                throw new \Exception("Trying to access $part as array, while it is a " . gettype($current));
            }
            if (!array_key_exists($part, $current)) {
                if ($index == sizeof($parts) - 1) { //last element in path, create it (with default null)
                    if ($part == "[]")
                        $part = sizeof($current);
                    else
                        $current[$part] = null;
                } else {
                    if ($part == "[]") {
                        throw new \Exception("[] must only be the last element of the path");
                    }
                    $current[$part] = [];
                }
            }
            $current =& $current[$part];
        }
        $current = $value;
        //echo "after:\n";
        //var_dump($this->config);
    }

    /**
     * Get the value of the config key specified
     *
     * @param string $path
     * @return array|mixed|null
     * @see Config::set() for syntax
     */
    public function get(string $path) {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                return null;
            }
            $current =& $current[$part];
        }
        return $current;
    }

    /**
     * Check if the config has a value for the given key
     *
     * In addition to the syntax of Config::set(), you can also search for a value in an array by specifying path[value].
     *
     * Note: this will return false if *any* part of the chain is missing.
     * @param string $path
     * @return bool
     * @see Config::set() for syntax
     */
    public function has(string $path): bool {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                //check if we are searching for a value - has("path[value]")
                if ($index == sizeof($parts) - 1 && substr($part, 0, 1) === "[" && substr($part, -1, 1) === "]") {
                    $value = substr($part, 1, -1);
                    $key = array_search($value, $current);
                    if ($key !== false) {
                        return true;
                    }
                }
                return false;
            }
            $current =& $current[$part];
        }
        return true;
    }

    /**
     * Delete a value from the configuration.
     *
     * In addition to the syntax of Config::set(), you can also search for a value in an array by specifying path[value].
     *
     * Note: this will barf if *any* part of the chain is missing.
     * @param string $path
     * @throws \Exception
     * @see Config::set() for syntax
     */
    public function unset(string $path) {
        $parts = explode(".", $path);
        $current =& $this->config;
        //echo "before:\n";
        //var_dump($this->config);
        foreach ($parts as $index => $part) {
            //echo "at $part, index $index, size ".sizeof($parts)."\n";
            if (is_numeric($part))
                $part = intval($part);
            if (!array_key_exists($part, $current)) {
                //check if we are searching for a value - unset("path[value]")
                if (substr($part, 0, 1) === "[" && substr($part, -1, 1) === "]") {
                    $value = substr($part, 1, -1);
                    $key = array_search($value, $current);
                    if ($key !== false) {
                        array_splice($current, $key, 1);
                        return;
                    }
                }
                throw new \Exception("Could not find part $part");
            }
            if ($index == sizeof($parts) - 1) {
                //Check if the array is sequential. If it is, need to re-order it, so use splice instead of unset.
                //See https://stackoverflow.com/a/173479 for the source of this if
                if (is_numeric($part) && array_keys($current) === range(0, count($current) - 1)) {
                    array_splice($current, $part, 1);
                } else {
                    unset($current[$part]);
                }
                return;
            }
            $current =& $current[$part];
        }
// @codeCoverageIgnoreStart
    }
// @codeCoverageIgnoreEnd

    /**
     * Replace the whole config
     * @param array $config
     */
    public function setAll(array $config) {
        //Ensure the version key is always, no matter the source, present and set
        if (!array_key_exists("version", $config))
            $config=array_merge_recursive(["version" => self::DEFAULT_VERSION],$config);
        $this->config = $config;
    }

    /**
     * Get the whole config
     * @return array
     */
    public function getAll() {
        return $this->config;
    }

}