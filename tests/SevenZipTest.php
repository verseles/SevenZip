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
    foreach ($defaultCompressFlags as $format) $testCases[$format] = [$format];

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
    $this->sevenZip->mmt(true);
    $this->testDir = realpath(__DIR__ . '/../test_files');
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

  private static function getPrivateProperty($object, $propertyName)
  {
    $reflection = new \ReflectionClass(get_class($object));
    $property   = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
  }

  public function testGetSevenZipPath(): void
  {
    $sevenZipPath = $this->sevenZip->getSevenZipPath();
    $this->assertIsString($sevenZipPath);
    $this->assertStringContainsString('7z', $sevenZipPath);
  }

  public function testSetSevenZipPath(): void
  {
    $customPath = '/custom/path/to/7z';
    $this->sevenZip->setSevenZipPath($customPath);
    $this->assertEquals($customPath, $this->sevenZip->getSevenZipPath());
  }

  public function testFlagrize(): void
  {
    $flags = [
      'a' => null,
      'b' => 'value1',
      'c' => 123,
    ];

    $expected = ['-a', '-b=value1', '-c=123'];
    $result   = $this->sevenZip->flagrize($flags);
    $this->assertEquals($expected, $result);
  }

  public function testGetAndSetAlwaysFlags(): void
  {
    $getAlwaysFlags = $this->getProtectedMethod($this->sevenZip, 'getAlwaysFlags');
    $setAlwaysFlags = $this->getProtectedMethod($this->sevenZip, 'setAlwaysFlags');

    $originalFlags = $getAlwaysFlags->invoke($this->sevenZip);
    $newFlags      = ['foo', 'bar' => 'baz'];
    $setAlwaysFlags->invoke($this->sevenZip, $newFlags);

    $this->assertEquals($newFlags, $getAlwaysFlags->invoke($this->sevenZip));

    $setAlwaysFlags->invoke($this->sevenZip, $originalFlags);
  }

  private static function getProtectedMethod($object, $methodName)
  {
    $reflection = new \ReflectionClass(get_class($object));
    $method     = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method;
  }

  public function testReset(): void
  {
    $this->sevenZip->addFlag('custom', 'value');
    $this->sevenZip->setProgressCallback(function () { });
    $this->sevenZip->setFormat('zip');
    $this->sevenZip->setTargetPath('/path/to/target');
    $this->sevenZip->setSourcePath('/path/to/source');

    $this->sevenZip->reset();

    $this->assertEmpty($this->sevenZip->getCustomFlags());
    $this->assertNull($this->sevenZip->getProgressCallback());
    $this->assertEquals('7z', $this->sevenZip->getFormat());
    $this->assertEmpty($this->sevenZip->getTargetPath());
    $this->assertEmpty($this->sevenZip->getSourcePath());
  }

  public function testProgress(): void
  {
    $callback = function () { };
    $this->sevenZip->progress($callback);
    $this->assertEquals($callback, $this->sevenZip->getProgressCallback());
  }

  public function testFasterAndSlower(): void
  {
    $this->sevenZip->format('zstd');
    $this->sevenZip->faster();
    $this->assertEquals(['mx' => 0, 'mmt' => 'on'], $this->sevenZip->getCustomFlags());

    $this->sevenZip->slower();
    $this->assertEquals(['mx' => 22, 'mmt' => 'on'], $this->sevenZip->getCustomFlags());

    $this->sevenZip->format('7z');
    $this->sevenZip->faster();
    $this->assertEquals(['mx' => 1, 'mmt' => 'on'], $this->sevenZip->getCustomFlags());

    $this->sevenZip->slower();
    $this->assertEquals(['mx' => 9, 'mmt' => 'on'], $this->sevenZip->getCustomFlags());
  }

  public function testUltraZip(): void
  {
    $this->sevenZip->format('zip');
    $this->sevenZip->ultra();
    $expected = [
      'mmt'   => 'on',
      'mx'    => 9,
      'mm'    => 'Deflate64',
      'mfb'   => 257,
      'mpass' => 15,
      'mmem'  => 28,
    ];
    $this->assertEquals($expected, $this->sevenZip->getCustomFlags());
  }

  public function testUltraZstd(): void
  {

    $this->sevenZip->format('zstd');
    $this->sevenZip->ultra();
    $expected = [
      'mmt' => 'on',
      'mx'  => 22,
    ];
    $this->assertEquals($expected, $this->sevenZip->getCustomFlags());
  }

  public function testUltra7z(): void
  {


    $this->sevenZip->format('7z');
    $this->sevenZip->ultra();
    $expected = [
      'mmt' => 'on',
      'mx'  => 9,
      'm0'  => 'lzma2',
      'mfb' => 64,
      'ms'  => 'on',
      'md'  => '32m',
    ];
    $this->assertEquals($expected, $this->sevenZip->getCustomFlags());

  }

  public function testCopy(): void
  {
    $this->sevenZip->copy();
    $expected = [
      'mmt' => 'on',
      'mx'  => 0,
      'm0'  => 'Copy',
      'mm'  => 'Copy',
      'myx' => 0,
    ];
    $this->assertEquals($expected, $this->sevenZip->getCustomFlags());
  }

  /**
   * @dataProvider compressAndExtractDataProvider
   */
  public function testCompress(string $format): void
  {
    $directory = $this->testDir . '/source';
    $archive   = $this->testDir . '/target/archive.' . $format;

    // Compress
    $this->sevenZip
      ->format($format)
      ->source(path: $directory)
      ->target(path: $archive)
      ->compress();

    $this->assertFileExists(filename: $archive);
  }

  /**
   * @dataProvider compressAndExtractDataProvider
   * @depends      testCompress
   */
  public function testExtract(string $format): void
  {
    $archive = $this->testDir . '/target/archive.' . $format;
    $target  = $this->testDir . '/extract/' . $format;
    self::clearDirectory($target);

    $this->sevenZip
      ->source(path: $archive)
      ->target(path: $target)
      ->extract();
    $this->assertFileExists(filename: $target . '/source/Avatart.svg');
    $this->assertFileExists(filename: $target . '/source/js_interop.dart');

    unlink($archive);
  }

  public function testAddAndRemoveFlag(): void
  {
    $flag  = 'mx';
    $value = 9;
    $this->sevenZip->addFlag($flag, $value);
    $customFlags = $this->sevenZip->getCustomFlags();
    $this->assertArrayHasKey($flag, $customFlags);
    $this->assertEquals($value, $customFlags[$flag]);

    $this->sevenZip->removeFlag($flag);
    $customFlags = $this->sevenZip->getCustomFlags();
    $this->assertArrayNotHasKey($flag, $customFlags);
  }

  /**
   * @depends testExtract
   */
  public function testSetProgressCallback(): void
  {
    $progressHistory = [];
    $callback        = function ($progress) use (&$progressHistory) {
      $progressHistory[] = $progress;
    };

    $directory = $this->testDir . '/source';
    $archive   = $this->testDir . '/target/archive.7z';

    $this->sevenZip
      ->setProgressCallback($callback)
      ->mx(9)
      ->compress('zstd', $directory, $archive);

    $this->assertNotEmpty($progressHistory);
    $this->assertEquals(100, last($progressHistory));
  }

  public function testGetDefaultCompressFlags(): void
  {
    $defaultFlags = self::getProtectedMethod($this->sevenZip, 'getDefaultCompressFlags');

    $this->assertEquals(['tzip'], $defaultFlags->invoke($this->sevenZip, 'zip'));
    $this->assertEquals(['t7z', 'm0' => 'lzma2'], $defaultFlags->invoke($this->sevenZip, '7z'));
    $this->assertEquals(['t7z', 'm0' => 'bzip2'], $defaultFlags->invoke($this->sevenZip, 'bzip2'));
    $this->assertEquals(['tmy_format'], $defaultFlags->invoke($this->sevenZip, 'my_format'));
  }
}
