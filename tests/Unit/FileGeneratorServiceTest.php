<?php

namespace App\Tests\Unit;

use App\Entity\Config;
use App\Service\FileGeneratorService;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FileGeneratorServiceTest extends KernelTestCase {
    /**
     * @var vfsStreamDirectory
     */
    private vfsStreamDirectory $vfsRoot;

    /**
     * Setup a vfs
     */
    public function setupFS(array $existingContent = []) {
        $this->vfsRoot = vfsStream::setup('root', null, $existingContent);
    }

    /**
     * Test generation of a simple file
     */
    public function testFileGenerate() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("testFile")->getContent()
        );
    }

    /**
     * Test generation of a simple file with chmod
     */
    public function testFileGenerateChmod() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
            "chmod" => 0777
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $service->writeFile("test.twig", [
            "target" => "testFile2",
            "chmod" => 0000
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("testFile"));
        $this->assertTrue($this->vfsRoot->hasChild("testFile2"));
        $this->assertEquals(
            2,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            0777,
            $this->vfsRoot->getChild("testFile")->getPermissions()
        );
        $this->assertEquals(
            0000,
            $this->vfsRoot->getChild("testFile2")->getPermissions()
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("testFile")->getContent()
        );
    }

    /**
     * Test generation of a simple file
     */
    public function testFileGenerateOverwrite() {
        $this->setupFS([
            "testFile" => "somecontent",
        ]);
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("testFile")->getContent()
        );
    }

    /**
     * Test generation of a simple file - if the if-condition resolves to false, nothing should happen
     */
    public function testFileGenerateIfFalse() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
            "if" => "false",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertFalse($this->vfsRoot->hasChild("testFile"));
        $this->assertEquals(
            0,
            sizeof($this->vfsRoot->getChildren())
        );
    }

    /**
     * Test generation of a simple file - if the if-condition
     * resolves to false and a file already exists, the file should get deleted
     */
    public function testFileGenerateDeleteExistingIfFalse() {
        $this->setupFS([
            "testFile" => "somecontent",
        ]);
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
            "if" => "false",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertFalse($this->vfsRoot->hasChild("testFile"));
        $this->assertEquals(
            0,
            sizeof($this->vfsRoot->getChildren())
        );
    }

    /**
     * Test if deletion of existing file as result of false if properly fails on permission errors
     */
    public function testFileGenerateDeleteExistingIfFalseError() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        VfsStream::newDirectory("folder", 0000)->at($this->vfsRoot);
        VfsStream::newFile("folder/testFile")->setContent("somecontent")->at($this->vfsRoot);
        $this->expectException(\RuntimeException::class);
        $service->writeFile("test.twig", [
            "target" => "folder/testFile",
            "if" => "false",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
    }

    /**
     * Test generation of a simple file - if the if-condition resolves to true, it should be created
     */
    public function testFileGenerateIfTrue() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "testFile",
            "if" => "true",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("testFile")->getContent()
        );
    }

    /**
     * Test generation of a file in a directory
     */
    public function testFileGenerateWithDirectory() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "folder/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("folder/testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("folder")->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("folder/testFile")->getContent()
        );
    }

    /**
     * Test generation of a file in a directory
     */
    public function testFileGenerateWithExistingDirectory() {
        $this->setupFS();
        VfsStream::newDirectory("folder")->at($this->vfsRoot);
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "folder/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("folder/testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("folder")->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("folder/testFile")->getContent()
        );
    }

    /**
     * Test generation of a file in a sub-directory
     */
    public function testFileGenerateWithTwoLevelDirectory() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "first/second/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("first/second/testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("first")->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("first")->getChild("second")->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("first/second/testFile")->getContent()
        );
    }

    /**
     * Test generation of a file in a sub-directory
     */
    public function testFileGenerateWithTwoLevelExistingDirectory() {
        $this->setupFS([
            "first" => [
                "second" => []
            ]
        ]);
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $service->writeFile("test.twig", [
            "target" => "first/second/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
        $this->assertTrue($this->vfsRoot->hasChild("first/second/testFile"));
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("first")->getChildren())
        );
        $this->assertEquals(
            1,
            sizeof($this->vfsRoot->getChild("first")->getChild("second")->getChildren())
        );
        $this->assertEquals(
            "value",
            $this->vfsRoot->getChild("first/second/testFile")->getContent()
        );
    }

    /**
     * Test that creating a directory fails if the directory exists as a file
     */
    public function testFileGenerateWithDirectoryMkdirErrorAlreadyExists() {
        $this->setupFS([
            "folder" => "somecontent"
        ]);
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        $this->expectException(\RuntimeException::class);
        $service->writeFile("test.twig", [
            "target" => "folder/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
    }

    /**
     * Test that creating a directory fails if the parent folder lacks permissions
     */
    public function testFileGenerateWithDirectoryMkdirErrorPermission() {
        $this->setupFS();
        self::bootKernel();
        $container = static::getContainer();
        $service = $container->get(FileGeneratorService::class);
        VfsStream::newDirectory("first", 0000)->at($this->vfsRoot);
        $this->expectException(\RuntimeException::class);
        $service->writeFile("test.twig", [
            "target" => "first/second/testFile",
        ], new Config([
            "test" => "value"
        ]), $this->vfsRoot->url());
    }
}