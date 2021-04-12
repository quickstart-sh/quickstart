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
            "foo"=>"bar",
        ]);
        ConfigFileService::ask($config, "foo", [
            "final" => true,
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEmpty($output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
            "foo"=>"bar",
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
     * Test String 5: no current value, default description, default value, not mandatory, no input
     *
     * Expect: nothing happens to config (only change to 1: default description is prompted)
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
            "default" => "foo",
        ], $this->input, $this->output);
        $output = $this->getOutput();
        $this->assertEquals("Please enter DESCRIPTION (default: DEFAULT DESCRIPTION): ", $output);
        $this->assertEquals([
            "version" => Config::DEFAULT_VERSION,
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
            "test"=>"foo",
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