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
    $defaultCompressFlags = ['zip', '7z', 'bzip2', 'tar'];

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

  /**
   * @covers \Verseles\SevenZip\SevenZip::getAlwaysFlags
   * @covers \Verseles\SevenZip\SevenZip::setAlwaysFlags
   * @return void
   * @throws \ReflectionException
   */
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

  /**
   * @covers \Verseles\SevenZip\SevenZip::reset
   */
  public function testReset(): void
  {
    $this->sevenZip
      ->addFlag('mmt', 'on')
      ->addFlag('mx', '9')
      ->setProgressCallback(function () {
      })
      ->setFormat('zip')
      ->setTargetPath('/path/to/target')
      ->setSourcePath('/path/to/source')
      ->setPassword('password')
      ->setEncryptNames(false)
      ->setTimeout(600)
      ->setIdleTimeout(240)
      ->setZipEncryptionMethod('AES128')
      ->forceTarBefore(true)
      ->keepFileInfoOnTar(false)
      ->setAlreadyTarred(true)
      ->autoUntar(false)
      ->deleteSourceAfterExtract(true);

    $this->sevenZip->reset();

    $this->assertEmpty($this->sevenZip->getCustomFlags());
    $this->assertNull($this->sevenZip->getProgressCallback());
    $this->assertSame(-1, $this->sevenZip->getLastProgress());
    $this->assertSame('7z', $this->sevenZip->getFormat());
    $this->assertNull($this->sevenZip->getTargetPath());
    $this->assertNull($this->sevenZip->getSourcePath());
    $this->assertNull($this->sevenZip->getPassword());
    $this->assertTrue($this->sevenZip->getEncryptNames());
    $this->assertSame(300, $this->sevenZip->getTimeout());
    $this->assertSame(120, $this->sevenZip->getIdleTimeout());
    $this->assertSame('AES256', $this->sevenZip->getZipEncryptionMethod());
    $this->assertFalse($this->sevenZip->shouldForceTarBefore());
    $this->assertTrue($this->sevenZip->shouldKeepFileInfoOnTar());
    $this->assertFalse($this->sevenZip->wasAlreadyTarred());
    $this->assertTrue($this->sevenZip->shouldAutoUntar());
    $this->assertFalse($this->sevenZip->shouldDeleteSourceAfterExtract());
  }

  /**
   * @covers \Verseles\SevenZip\SevenZip::progress
   * @return void
   */
  public function testProgress(): void
  {
    $callback = function () { };
    $this->sevenZip->progress($callback);
    $this->assertEquals($callback, $this->sevenZip->getProgressCallback());
  }

  /**
   * @covers \Verseles\SevenZip\SevenZip::faster
   * @covers \Verseles\SevenZip\SevenZip::slower
   */
  public function testCompressionLevels(): void
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

  /**
   * @covers \Verseles\SevenZip\SevenZip::ultra
   */
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

  /**
   * @covers \Verseles\SevenZip\SevenZip::ultra
   */
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

  /**
   * @covers \Verseles\SevenZip\SevenZip::ultra
   */
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

  /**
   * @covers \Verseles\SevenZip\SevenZip::copy
   */
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
   * @covers       \Verseles\SevenZip\SevenZip::compress
   */
  public function testCompress(string $format): void
  {
    $directory = $this->testDir . '/source';
    $archive   = $this->testDir . '/target/archive.' . $format;

    // Compress
    $this->sevenZip
      ->format($format)
      ->faster()
      ->source(path: $directory)
      ->target(path: $archive)
      ->compress();

    $this->assertFileExists(filename: $archive);
  }

  /**
   * @dataProvider compressAndExtractDataProvider
   * @covers       \Verseles\SevenZip\SevenZip::extract
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
    $this->assertFileExists(filename: $target . '/source/js_interop_usage.md');

    unlink($archive);
  }

  /**
   * @covers \Verseles\SevenZip\SevenZip::addFlag
   * @covers \Verseles\SevenZip\SevenZip::removeFlag
   */
  public function testFlagManagement(): void
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

  public function testSetProgressCallback(): void
  {
    $progressHistory = [];
    $callback        = function ($progress) use (&$progressHistory) {
      $progressHistory[] = $progress;
    };

    $directory = $this->testDir . '/source';
    $archive   = $this->testDir . '/target/archive.7z';

    $this->sevenZip
      ->format('7z')
      ->faster()
      ->source(path: $directory)
      ->target(path: $archive)
      ->setProgressCallback($callback)
      ->compress();

    $this->assertNotEmpty($progressHistory);
    $this->assertEquals(100, end($progressHistory));
  }

  /**
   * Test encrypting and decrypting a file.
   * @covers \Verseles\SevenZip\SevenZip::encrypt
   * @covers \Verseles\SevenZip\SevenZip::decrypt
   * @return void
   */
  public function testEncryptAndDecrypt(): void
  {
    $password      = 'my_secret_password';
    $sourceFile    = $this->testDir . '/source/Avatart.svg';
    $encryptedFile = $this->testDir . '/target/test.encrypted.7z';
    $decryptedFile = $this->testDir . '/target/';

    // Compress and encrypt the file
    $this->sevenZip
      ->faster()
      ->encrypt($password)
      ->source($sourceFile)
      ->target($encryptedFile)
      ->compress();

    $this->assertFileExists($encryptedFile);

    // Decrypt and extract the file
    $this->sevenZip->decrypt($password)
      ->source($encryptedFile)
      ->target($decryptedFile)
      ->extract();

    $this->assertFileExists($decryptedFile);
    $this->assertFileEquals($sourceFile, $decryptedFile . 'Avatart.svg');
  }

  /**
   * Test encrypting a ZIP file with a specific encryption method.
   *
   * @return void
   */
  public function testEncryptZipWithEncryptionMethod(): void
  {
    $password  = 'my_secret_password';
    $format    = 'zip';
    $directory = $this->testDir . '/source';
    $archive   = $this->testDir . '/target/archive.' . $format;

    // Compress and encrypt the ZIP file with AES256 encryption
    $this->sevenZip
      ->format($format)
      ->encrypt($password)
      ->setZipEncryptionMethod('AES256')
      ->faster()
      ->source($directory)
      ->target($archive)
      ->compress();

    $this->assertFileExists($archive);

  }

  /**
   * @covers \Verseles\SevenZip\SevenZip::getSupportedFormatExtensions
   * @covers \Verseles\SevenZip\SevenZip::checkSupport
   */
  public function testSupportedFormatsFunctions(): void
  {
    $expectedFormats = ['zip', 'tar', '7z'];

    foreach ($expectedFormats as $format) {
      $this->assertTrue($this->sevenZip->checkSupport($format), 'Check using one format failure');
    }

    $this->assertTrue($this->sevenZip->checkSupport($expectedFormats), 'Check using array failure');

    $this->assertFalse($this->sevenZip->checkSupport('my_super_futurist_format'), 'Check using unknown format failure');
  }

  /**
   * Test excluding specific files from the archive.
   *
   * @return void
   */
  public function testExclude(): void
  {
    $format    = 'zip';
    $directory = $this->testDir . '/source/*';
    $archive   = $this->testDir . '/target/exclude_archive.' . $format;
    $exclude   = ['Avatart.svg', 'js_interop_usage.md']; // Specify files to exclude

    $extractPath = $this->testDir . '/extract';

    $this->assertFileDoesNotExist($archive);

    // Exclude specific files
    $this->sevenZip
      ->format($format)
      ->faster()
      ->exclude($exclude)
      ->source($directory)
      ->target($archive)
      ->compress();

    $this->assertFileExists($archive);

    $this->sevenZip
      ->source($archive)
      ->target($extractPath)
      ->extract();

    $this->assertFileDoesNotExist($extractPath . '/Avatart.svg');
    $this->assertFileDoesNotExist($extractPath . '/js_interop_usage.md');
    $this->assertFileExists($extractPath . '/js-types.md');

    // clear directory
    self::clearDirectory($extractPath);
  }

  /**
   * Test including specific files in the archive.
   * @test
   * @return void
   */
  public function testInclude(): void
  {
    $format  = '7z';
    $dir     = $this->testDir . '/source/*';
    $archive = $this->testDir . '/target/include_archive.' . $format;
    $include = '*.avg';            // Specify files to include
    $exclude = ['*.md', '*.pdf'];  // Specify files to exclude

    $extractPath = $this->testDir . '/extract';

    $this->assertFileDoesNotExist($archive);

    // Include specific files
    $this->sevenZip
      ->format($format)
      ->faster()
      ->include($include)
      ->exclude($exclude)
      ->source($dir)
      ->target($archive)
      ->compress();

    $this->assertFileExists($archive);

    // Extract the archive to verify the inclusion
    $this->sevenZip
      ->source($archive)
      ->target($extractPath)
      ->extract();

    $this->assertFileExists($extractPath . '/Avatart.svg');
    $this->assertFileDoesNotExist($extractPath . '/js_interop_usage.md');
    $this->assertFileDoesNotExist($extractPath . '/js-types.md');
  }

  public function testTarBeforeImplicit(): void
  {
    $tarPath = $this->testDir . '/target/archive.7z';
    $this->sevenZip
      ->format('tar.7z')
      ->faster()
      ->source($this->testDir . '/source/*')
      ->target($tarPath)
      ->compress();

    $this->assertFileExists($tarPath);
  }

  /**
   * @covers \Verseles\SevenZip\SevenZip::tarBefore
   * @covers \Verseles\SevenZip\SevenZip::fileInfo
   * @covers \Verseles\SevenZip\SevenZip::fileList
   * @return void
   */
  public function testTarBeforeExplicit(): string
  {
    $tarPath = $this->testDir . '/target/archive.tar.7z';
    $this->sevenZip
      ->format('7z')
      ->faster()
      ->source($this->testDir . '/source/*')
      ->target($tarPath)
      ->tarBefore()
      ->compress();


    $this->assertFileExists($tarPath);

    $sz = $this->sevenZip->source($tarPath);

    $fileInfo = $sz->fileInfo();
    $fileList = $sz->fileList();

    $this->assertIsArray($fileInfo);
    $this->assertCount(1, $fileList);

    return $tarPath;
  }

  /**
   * @depends testTarBeforeExplicit
   * @covers  \Verseles\SevenZip\SevenZip::autoUntar
   * @covers  \Verseles\SevenZip\SevenZip::shouldAutoUntar
   * @return void
   */
  public function testAutoUntar($tarPath)
  {
    $this->assertFileExists($tarPath);
    $extractPath = $this->testDir . '/extract/auto_untar/';
    $this->sevenZip
      ->source($tarPath)
      ->target($extractPath)
      ->extract();

    $this->assertFileExists($extractPath . '/Avatart.svg');
  }

  /**
   * @depends testTarBeforeExplicit
   * @covers  \Verseles\SevenZip\SevenZip::autoUntar
   * @covers  \Verseles\SevenZip\SevenZip::shouldAutoUntar
   * @return void
   */
  public function testNotAutoUntar($tarPath)
  {
    $this->assertFileExists($tarPath);
    $extractPath = $this->testDir . '/extract/not_auto_untar/';
    $this->sevenZip
      ->source($tarPath)
      ->target($extractPath)
      ->autoUntar(false)
      ->extract();

    $this->assertFileDoesNotExist($extractPath . '/Avatart.svg');
  }

  /**
   * @depends testTarBeforeExplicit
   * @covers  \Verseles\SevenZip\SevenZip::deleteSourceAfterExtract
   * @covers  \Verseles\SevenZip\SevenZip::shouldDeleteSourceAfterExtract
   * @return void
   */
  public function testDeleteSourceAfterExtract($tarPath)
  {
    $this->assertFileExists($tarPath);
    $extractPath = $this->testDir . '/extract/delete_source/';
    $this->sevenZip
      ->source($tarPath)
      ->deleteSourceAfterExtract()
      ->target($extractPath)
      ->extract();

    $this->assertFileDoesNotExist($tarPath);

  }


}
