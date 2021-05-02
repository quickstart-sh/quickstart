<?php

namespace App\Tests\Unit;

use App\Entity\Config;
use App\Service\ConfigFileService;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\TesterTrait;

class ConfigFileServiceTest extends TestCase {
    /**
     * @var Input
     */
    private $input;
    /**
     * @var StreamOutput
     */
    private $output;

    /**
     * @var vfsStreamDirectory
     */
    private $vfsRoot;

    /**
     * Set up memory input and output streams
     * @param $inputs
     */
    private function setupIO($inputs) {
        $this->input = new ArrayInput([]);
        /**
         * @see TesterTrait::createStream()
         */
        $stream = fopen('php://memory', 'r+', false);
        foreach ($inputs as $input) {
            fwrite($stream, $input . \PHP_EOL);
        }
        rewind($stream);
        $this->input->setStream($stream);
        //$this->input->setInteractive(true);
        /**
         * @see TesterTrait::initOutput()
         */
        $this->output = new StreamOutput(fopen('php://memory', 'w', false));
    }

    /**
     * Get the captured output
     * @return string
     * @see TesterTrait::getDisplay()
     */
    private function getOutput(): string {
        rewind($this->output->getStream());
        $display = stream_get_contents($this->output->getStream());
        $display = str_replace(\PHP_EOL, "\n", $display);
        return $display;
    }

    /**
     * Setup a vfs
     */
    public function setupFS(array $existingContent = []) {
        $this->vfsRoot = vfsStream::setup('root', null, $existingContent);
    }

    /**
     * Close down the IO streams to avoid leaks
     */
    public function tearDown(): void {
        if ($this->input instanceof Input) {

            $inputStream = $this->input->getStream();
            if (is_resource($inputStream))
                fclose($inputStream);
            $this->input = null;
        }
        if ($this->output instanceof Output) {
            $outputStream = $this->output->getStream();
            if (is_resource($outputStream))
                fclose($outputStream);
            $this->output = null;
        }
    }

    /**
     * Test if initialize() creates a valid empty config file
     */
    public function testInitialize() {
        $this->setupFS();
        $service = new ConfigFileService();
        $service->initialize($this->vfsRoot->url() . "/" . ConfigFileService::CONFIG_FILE);
        $this->assertTrue($this->vfsRoot->hasChild(ConfigFileService::CONFIG_FILE));
        $this->assertEquals(
            "version: 1\n\n",
            $this->vfsRoot->getChild(ConfigFileService::CONFIG_FILE)->getContent()
        );
    }

    /**
     * Test if persist() writes a valid config
     */
    public function testPersistError() {
        $this->setupFS();
        //We're using file_put_contents under the hood... means any error can make it barf
        //The easiest to provoke is a write-protected file, so go for it
        vfsStream::newFile(ConfigFileService::CONFIG_FILE, 0000)
            ->withContent('notoverwritten')
            ->at($this->vfsRoot);
        $config = new Config();
        $service = new ConfigFileService();
        $this->expectException(\Exception::class);
        $service->persist($this->vfsRoot->url() . "/" . ConfigFileService::CONFIG_FILE, $config);
    }

    /**
     * Test if persist() barfs if it can't write to file
     */
    public function testPersist() {
        $this->setupFS();
        $config = new Config([
            "foo" => "bar",
            "baz" => [
                "qux",
                "quux",
            ]
        ]);
        $service = new ConfigFileService();
        $service->persist($this->vfsRoot->url() . "/" . ConfigFileService::CONFIG_FILE, $config);
        //If this breaks, always check for indentation... Symfony at this time uses four whitespaces
        $this->assertEquals(
            "version: 1\nfoo: bar\nbaz:\n    - qux\n    - quux\n\n",
            $this->vfsRoot->getChild(ConfigFileService::CONFIG_FILE)->getContent()
        );
    }

    /**
     * Test if load() successfully loads an example file
     */
    public function testSuccessfulLoad() {
        $this->setupFS([
            ConfigFileService::CONFIG_FILE => "version: 1\nfoo: bar\nbaz:\n  - qux\n  - quux\n"
        ]);
        $service = new ConfigFileService();
        $config = $service->load($this->vfsRoot->url() . "/" . ConfigFileService::CONFIG_FILE);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => "bar",
            "baz" => [
                "qux",
                "quux",
            ]
        ], $config->getAll());
    }

    /**
     * Test if load() barfs on not-existing file
     */
    public function testLoadErrorNotExisting() {
        $this->setupFS([
            ConfigFileService::CONFIG_FILE => "version: 1\nfoo: bar\nbaz:\n  - qux\n  - quux\n"
        ]);
        $service = new ConfigFileService();
        $this->expectException(\Exception::class);
        $service->load($this->vfsRoot->url() . "/THISWILLBARF");
    }

    /**
     * Test if load() barfs on mis-formatted file
     */
    public function testLoadErrorMalformed() {
        $this->setupFS([
            ConfigFileService::CONFIG_FILE => "THISWILLBARF"
        ]);
        $service = new ConfigFileService();
        $this->expectException(\Exception::class);
        $service->load($this->vfsRoot->url() . "/" . ConfigFileService::CONFIG_FILE);
    }

    /**
     * Make sure a question that has its if-condition evaluate to false doesn't get asked and the config is unchanged
     * @throws \Exception
     */
    public function testIfFalse() {
        $this->setupIO([]);
        $config = new Config();
        ConfigFileService::ask($config, "test", [
            "if" => "false",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEmpty($output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Make sure a question that has its if-condition evaluate to false doesn't get asked and the config is unchanged
     * @throws \Exception
     */
    public function testFinal() {
        $this->setupIO([]);
        $config = new Config([
            "foo" => "bar",
        ]);
        ConfigFileService::ask($config, "foo", [
            "final" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEmpty($output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo" => "bar",
        ], $config->getAll());
    }

    /**
     * Make sure a question that has an unsupported type barfs
     * @throws \Exception
     */
    public function testUnknownTypeError() {
        $this->setupIO([]);
        $config = new Config();
        $this->expectException(\Exception::class);
        ConfigFileService::ask($config, "test", [
            "type" => "THISWILLBARF",
        ], $this->input, $this->output);
    }

    /**
     * Test String 1: no current value, no default description, no default value, not mandatory, no input
     *
     * Expect: nothing happens to config
     * @throws \Exception
     */
    public function testString1() {
        $this->setupIO([""]);
        $config = new Config();
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION: ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Test String 2: current value, no default description, no default value, not mandatory, no input
     *
     * Expect: nothing happens to config (only change to 1: current value is prompted)
     * @throws \Exception
     */
    public function testString2() {
        $this->setupIO([""]);
        $config = new Config([
            "test" => "foo",
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (current: foo): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Test String 3: current value, default description, no default value, not mandatory, no input
     *
     * Expect: nothing happens to config (only change to 2: default description is prompted)
     * @throws \Exception
     */
    public function testString3() {
        $this->setupIO([""]);
        $config = new Config([
            "test" => "foo",
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "defaultDescription" => "DEFAULT DESCRIPTION",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION, current: foo): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Test String 4: no current value, default description, no default value, not mandatory, no input
     *
     * Expect: nothing happens to config (only change to 1: default description is prompted)
     * @throws \Exception
     */
    public function testString4() {
        $this->setupIO([""]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "defaultDescription" => "DEFAULT DESCRIPTION",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Test String 5: no current value, default description, default value with eval, mandatory, no input
     *
     * Expect: default is persisted to config
     * @throws \Exception
     */
    public function testString5() {
        $this->setupIO([""]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "defaultDescription" => "DEFAULT DESCRIPTION",
            "default" => "'foo' . 'bar'",
            "defaultEval" => true,
            "mandatory" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test" => "foobar"
        ], $config->getAll());
    }

    /**
     * Test String 6: current value, no default description, no default value, mandatory, no input
     *
     * Expect: Existing value is kept
     * @throws \Exception
     */
    public function testString6() {
        $this->setupIO([""]);
        $config = new Config([
            "test" => "foo",
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "mandatory" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (current: foo): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test" => "foo",
        ], $config->getAll());
    }

    /**
     * Test String 7: no current value, no default description, no default value, mandatory, no input (1st)/input(2nd)
     *
     * Expect: Input is accepted
     * @throws \Exception
     */
    public function testString7() {
        $this->setupIO(["", "foo"]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "mandatory" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals(
            "Please enter DESCRIPTION: Invalid answer , please try again\n" .
            "Please enter DESCRIPTION: ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test" => "foo",
        ], $config->getAll());
    }

    /**
     * Test String 8: no current value, default description, default value, mandatory, no input
     *
     * Expect: default value is accepted
     * @throws \Exception
     */
    public function testString8() {
        $this->setupIO([""]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "defaultDescription" => "DEFAULT DESCRIPTION",
            "default" => "foo",
            "mandatory" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test" => "foo",
        ], $config->getAll());
    }

    /**
     * Test String 8: no current value, default description, default value, mandatory, no input
     *
     * Expect: default value is accepted
     * @throws \Exception
     */
    public function testString9() {
        $this->setupIO([""]);
        $config = new Config([
            "test" => "foo",
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "mandatory" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (current: foo): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test" => "foo",
        ], $config->getAll());
    }

    /**
     * Test String 10: no current value, default description, default value, not mandatory, no input
     *
     * Expect: nothing happens to config (only change to 1: default description is prompted)
     * @throws \Exception
     */
    public function testString10() {
        $this->setupIO([""]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "defaultDescription" => "DEFAULT DESCRIPTION",
            "default" => "foo",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
        ], $config->getAll());
    }

    /**
     * Test single select based upon the test fixtures
     * @dataProvider singleSelectDataProvider
     * @param array $startConfig
     * @param array $expectedConfig
     * @param array $question
     * @param array $input
     * @param string $expectedOutput
     * @throws \Exception
     */
    public function testSingleSelect(array $startConfig, array $expectedConfig, array $question, array $input, string $expectedOutput) {
        $this->setupIO($input);
        $config = new Config($startConfig);
        ConfigFileService::ask($config, "test", $question, $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals($expectedOutput, $output);
        $this->assertEquals($expectedConfig, $config->getAll());
    }

    /**
     * @return array
     */
    public function singleSelectDataProvider(): array {
        $cases = [];
        //Not mandatory, no current value, no input, no default, expect: no change
        $cases[0] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ]
            ],
            [""],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n  [3] None (default)\n > \n",
        ];
        //mandatory, no current value, valid input, no default, expect: choice persisted
        $cases[1] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 1[K\n",
        ];
        //mandatory, no current value, invalid then valid input, no default, expect: choice persisted
        $cases[2] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["", "1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > " .
            "\nValue \"\" is invalid\nPlease select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > 1[K\n",
        ];
        //mandatory, current value, valid input, no default, expect: choice persisted
        $cases[3] = [
            [
                "test" => "quux"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["1"],
            "Please select DESCRIPTION (current: quuux): \n  [0] bar\n  [1] qux\n  [2] quuux\n > 1[K\n",
        ];
        //mandatory, current value, valid input equals current, no default, expect: choice persisted
        $cases[4] = [
            [
                "test" => "quux"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "quux",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["2"],
            "Please select DESCRIPTION (current: quuux): \n  [0] bar\n  [1] qux\n  [2] quuux\n > 2[K\n",
        ];
        //mandatory, current value that doesn't exist (anymore), valid input, no default, expect: choice persisted
        $cases[5] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "quux",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["2"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > 2[K\n",
        ];
        //not mandatory, current value that doesn't exist (anymore), valid input, no default, expect: choice persisted
        $cases[6] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "quux",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
            ],
            ["2"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n  [3] None (default)\n > 2[K\n",
        ];
        //not mandatory, current value that doesn't exist (anymore), valid input, default equals input, expect: choice persisted
        $cases[7] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "foo",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "default" => "foo",
            ],
            ["0"],
            "Please select DESCRIPTION (current: bar): \n  [0] bar (default)\n  [1] qux\n  [2] quuux\n  [3] None\n > 0[K\n",
        ];
        //not mandatory, current value that doesn't exist (anymore), valid input, default not equals input, expect: choice persisted
        $cases[8] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "foo",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "default" => "quux",
            ],
            ["0"],
            "Please select DESCRIPTION (current: quuux): \n  [0] bar\n  [1] qux\n  [2] quuux (default)\n  [3] None\n > 0[K\n",
        ];
        //not mandatory, current value that doesn't exist (anymore), no input, default, expect: choice persisted
        $cases[9] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "foo"
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "default" => "foo",
            ],
            [""],
            "Please select DESCRIPTION (current: bar): \n  [0] bar (default)\n  [1] qux\n  [2] quuux\n  [3] None\n > \n",
        ];
        //not mandatory, current value that doesn't exist (anymore), no input, no default, expect: choice persisted
        $cases[10] = [
            [
                "test" => "banana"
            ],
            [
                "version" => Config::DEFAULT_VERSION,
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
            ],
            [""],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n  [3] None (default)\n > \n",
        ];
        //mandatory, no current value, valid input, side effects. expect: choice persisted, side effects honored
        $cases[11] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "foo",
                "minions"=>"banana",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "set"=>[
                            "minions"=>"banana"
                        ]
                    ]
                ]
            ],
            ["0"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 0[K\n",
        ];
        //mandatory, no current value, valid input, side effects for other option. expect: choice persisted, no side effects
        $cases[12] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "set"=>[
                            "minions"=>"banana"
                        ]
                    ]
                ]
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 1[K\n",
        ];
        //mandatory, no current value, valid input, side effects. expect: choice persisted, side effects honored (unset)
        $cases[13] = [
            [
                "version" => Config::DEFAULT_VERSION,
                "minions"=>"banana",
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "foo",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "set"=>[
                            "minions"=>null
                        ]
                    ]
                ]
            ],
            ["0"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 0[K\n",
        ];
        //mandatory, no current value, valid input, side effects for other option. expect: choice persisted, no side effects (not unset)
        $cases[14] = [
            [
                "version" => Config::DEFAULT_VERSION,
                "minions"=>"banana",
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
                "minions"=>"banana",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "set"=>[
                            "minions"=>null
                        ]
                    ]
                ]
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 1[K\n",
        ];
        //mandatory, no current value, valid input, option hidden by side effect. expect: choice persisted, hidden option hidden
        $cases[15] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "quux",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "if"=>"false",
                    ]
                ]
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] qux\n  [1] quux\n > 1[K\n",
        ];
        //mandatory, no current value, valid input, option shown by side effect. expect: choice persisted, hidden option shown
        $cases[16] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => "baz",
            ],
            [
                "type" => "select_single",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
                "optionsConfiguration"=>[
                    "foo"=>[
                        "if"=>"true",
                    ]
                ]
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 1[K\n",
        ];
        return $cases;
    }

    /**
     * Test multi select based upon the test fixtures
     * @dataProvider multiSelectDataProvider
     * @param array $startConfig
     * @param array $expectedConfig
     * @param array $question
     * @param array $input
     * @param string $expectedOutput
     * @throws \Exception
     */
    public function testMultiSelect(array $startConfig, array $expectedConfig, array $question, array $input, string $expectedOutput) {
        $this->setupIO($input);
        $config = new Config($startConfig);
        ConfigFileService::ask($config, "test", $question, $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals($expectedOutput, $output);
        $this->assertEquals($expectedConfig, $config->getAll());
    }

    /**
     * @return array
     */
    public function multiSelectDataProvider(): array {
        $cases = [];
        //Not mandatory, no current value, no input, expect: no change
        $cases[0] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ]
            ],
            [""],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n  [3] None (default)\n > \n",
        ];
        //mandatory, no current value, valid input (single), expect: choice persisted
        $cases[1] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["baz"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
            ],
            ["1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 1[K\n",
        ];
        //mandatory, no current value, valid input (multi), expect: choice persisted
        $cases[2] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["foo", "quux"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quux",
                ],
                "mandatory" => true,
            ],
            ["0,2"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quux\n > 0[K,[K2[K\n",
        ];

        //mandatory, no current value, invalid (empty) then valid input, expect: choice persisted
        $cases[3] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["baz"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["", "1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > " .
            "\nValue \"\" is invalid\nPlease select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > 1[K\n",
        ];
        //mandatory, no current value, invalid (not-existing) then valid input, expect: choice persisted
        $cases[4] = [
            [],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["baz"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["THISWILLBARF", "1"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > T[KH[KI[KS[KW[KI[KL[KL[KB[KA[KR[KF[K" .
            "\nValue \"THISWILLBARF\" is invalid\nPlease select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > 1[K\n",
        ];

        //mandatory, current value, valid input, expect: choice persisted
        $cases[5] = [
            [
                "test" => ["quux"]
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["quux", "baz"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["1"],
            "Please select DESCRIPTION (current: quuux): \n  [0] bar\n  [1] qux\n  [2] None (default)\n > 1[K\n",
        ];
        //mandatory, current value, none input, expect: no change
        $cases[6] = [
            [
                "test" => ["quux"]
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["quux"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["2"],
            "Please select DESCRIPTION (current: quuux): \n  [0] bar\n  [1] qux\n  [2] None (default)\n > 2[K\n",
        ];

        //mandatory, current value that doesn't exist (anymore), valid input, expect: choice persisted
        $cases[7] = [
            [
                "test" => ["banana"]
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["quux"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
                "mandatory" => true,
            ],
            ["2"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n > 2[K\n",
        ];

        //not mandatory, current value that doesn't exist (anymore), valid input, expect: choice persisted
        $cases[8] = [
            [
                "test" => ["banana"]
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => ["quux"],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
            ],
            ["2"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n  [3] None (default)\n > 2[K\n",
        ];

        //not mandatory, current value that doesn't exist (anymore), none input, expect: empty?
        $cases[9] = [
            [
                "test" => ["banana"]
            ],
            [
                "version" => Config::DEFAULT_VERSION,
                "test" => [],
            ],
            [
                "type" => "select_multi",
                "description" => "DESCRIPTION",
                "options" => [
                    "foo" => "bar",
                    "baz" => "qux",
                    "quux" => "quuux",
                ],
            ],
            ["3"],
            "Please select DESCRIPTION: \n  [0] bar\n  [1] qux\n  [2] quuux\n  [3] None (default)\n > 3[K\n",
        ];
        return $cases;
    }


    /**
     * Test path override
     *
     * Expect: Input is accepted and stored in the override
     * @throws \Exception
     */
    public function testPathOverride() {
        $this->setupIO(["foo"]);
        $config = new Config([
        ]);
        ConfigFileService::ask($config, "test", [
            "type" => "string",
            "description" => "DESCRIPTION",
            "pathOverride" => "test2"
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals(
            "Please enter DESCRIPTION: ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "test2" => "foo",
        ], $config->getAll());
    }
}