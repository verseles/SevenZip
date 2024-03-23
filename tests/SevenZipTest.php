<?php

namespace Verseles\SevenZip\Tests;

use PHPUnit\Framework\TestCase;
use Verseles\SevenZip\SevenZip;

class SevenZipTest extends TestCase
{
  private $sevenZip;
  private $testDir;

  public static function compressAndExtractDataProvider(): array
  {
    $defaultCompressFlags = ['zip', '7z', 'bzip2'];

    $testCases = [];
    foreach ($defaultCompressFlags as $format) {
      $testCases[$format] = [$format];
    }

    return $testCases;
  }

  public static function tearDownAfterClass(): void
  {
    parent::tearDownAfterClass();

    $testDir = realpath(__DIR__ . '/../test_files');
    self::clearDirectory($testDir . '/target');
    self::clearDirectory($testDir . '/extract');
  }

  protected function setUp(): void
  {
    $this->sevenZip = new SevenZip();
    $this->testDir  = realpath(__DIR__ . '/../test_files');
  }

  public static function clearDirectory($directory)
  {
    $files = glob($directory . '/*');
    foreach ($files as $file) {
      if (is_file($file)) {
        unlink($file);
      } elseif (is_dir($file)) {
        self::clearDirectory($file);
        rmdir($file);
      }
    }
  }

  /**
   * @dataProvider compressAndExtractDataProvider
   */
  public function testCompress(string $format): void
  {
    $sourcePath  = $this->testDir . '/source';
    $archivePath = $this->testDir . '/target/archive.' . $format;

    // Compress
    $this->sevenZip->compress(format: $format, archivePath: $archivePath, sourcePath: $sourcePath);
    $this->assertFileExists(filename: $archivePath);
  }

  /**
   * @dataProvider compressAndExtractDataProvider
   * @depends      testCompress
   */
  public function testExtract(string $format): void
  {
    $archivePath = $this->testDir . '/target/archive.' . $format;
    $extractPath = $this->testDir . '/extract/' . $format;
    self::clearDirectory($extractPath);


    // Extract
    $this->sevenZip->extract(format: $format, archivePath: $archivePath, extractPath: $extractPath);
    $this->assertFileExists(filename: $extractPath . '/source/Avatart.svg');
    $this->assertFileExists(filename: $extractPath . '/source/js_interop.dart');

    unlink($archivePath);
  }

  public function testAddAndRemoveFlag(): void
  {
    $flag = '-mx=9';
    $this->sevenZip->addCompressFlag($flag);
    $this->assertContains($flag, self::getPrivateProperty($this->sevenZip, 'customCompressFlags'));

    $this->sevenZip->removeCompressFlag($flag);
    $this->assertNotContains($flag, self::getPrivateProperty($this->sevenZip, 'customCompressFlags'));
  }

  private static function getPrivateProperty($object, $propertyName)
  {
    $reflection = new \ReflectionClass(get_class($object));
    $property   = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
  }
}
