<?php

namespace Verseles\SevenZip;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SevenZip
{
  private string $sevenZipPath;

  private array $alwaysFlags          = ['-bsp1', '-y'];
  private array $defaultCompressFlags = [
    'zip'   => ['-tzip'],
    '7z'    => ['-t7z'],
    'lzma2' => ['-t7z'],
    'lz4'   => ['-t7z', '-m0=lz4'],
    'lz5'   => ['-t7z', '-m0=lz5'],
    'bz2'   => ['-t7z', '-m0=bzip2'],
    'bzip2' => ['-t7z', '-m0=bzip2'],
    'zstd'  => ['-t7z', '-m0=zstd'],
    'zst'   => ['-t7z', '-m0=zstd'],
    'tar'   => ['-ttar'], // will make file bigger than source

    // @TODO add custom support for gz, xz, etc by using tar flags
    // @TODO use tar to keep original file permissions and others attributes
    // @TODO add extract custom flags
  ];
  private       $customCompressFlags  = [];
  private       $progressCallback;

  /**
   *
   * @param string|null $sevenZipPath Path to 7z executable (optional).
   * @throws \RuntimeException If 7z executable is not found.
   */
  public function __construct(string $sevenZipPath = null)
  {
    if ($sevenZipPath === null) {
      $finder       = new ExecutableFinder();
      $sevenZipPath = $finder->find('7z');
    }

    if ($sevenZipPath === null) {
      throw new \RuntimeException('Executable 7z not found.');
    }

    $this->sevenZipPath = $sevenZipPath;
  }

  /**
   * Compress a file or directory
   *
   * @param string $format Format of the archive
   * @param string $archivePath Path to the file or directory to compress
   * @param string $sourcePath Path to the compressed archive
   */
  public function compress(string $format, string $archivePath, string $sourcePath): void
  {
    $command = [
      $this->sevenZipPath,
      'a',
      ...$this->alwaysFlags,
      ...$this->getDefaultCompressFlags($format),
      ...$this->customCompressFlags,
      $archivePath,
      $sourcePath,
    ];


    $this->runCommand($command);
  }

  /**
   * Get default flags for a given format.
   *
   * @param string $format Format of the archive
   * @return array Default flags
   */
  private function getDefaultCompressFlags(string $format): array
  {
    return $this->defaultCompressFlags[$format] ?? [];
  }

  /**
   * Run a command and parse its output.
   *
   * @param array $command Command to run
   * @throws \RuntimeException If the command fails
   */
  private function runCommand(array $command): void
  {
    $process = new Process($command);
    $process->run(function ($type, $buffer) {
      if ($type === Process::OUT) {
        $this->parseProgress($buffer);
      }
    });

    if (!$process->isSuccessful()) {
      throw new \RuntimeException($process->getErrorOutput());
    }
  }

  /**
   * Parses the progress from the command output and calls the progress callback.
   *
   * @param string $output Output of the command
   */
  private function parseProgress(string $output): static
  {
    if ($this->progressCallback === null) {
      return $this;
    }

    $lines = explode("\n", $output);
    foreach ($lines as $line) {
      if (preg_match('/(\d+)%/', $line, $matches)) {
        $progress = intval($matches[1]);
        call_user_func($this->progressCallback, $progress);
      }
    }

    return $this;
  }

  /**
   * Decompress an archive
   *
   * @param string $format Format of the archive to extract
   * @param string $archivePath Path to the archive
   * @param string $extractPath Path to extract the archive
   */
  public function extract(string $format, string $archivePath, string $extractPath): void
  {
    $command = [
      $this->sevenZipPath,
      'x',
      ...$this->alwaysFlags,
      $archivePath,
      '-o' . $extractPath,
    ];

    $this->runCommand($command);
  }

  /**
   * Adds a flag to the list of flags.
   *
   * @param string $flag Flag to add.
   */
  public function addCompressFlag(string $flag): static
  {
    $this->customCompressFlags[] = $flag;

    return $this;
  }

  /**
   * Removes a flag from the list of flags.
   *
   * @param string $flag Flag to remove.
   */
  public function removeCompressFlag(string $flag): static
  {
    $index = array_search($flag, $this->customCompressFlags);
    if ($index !== false) {
      unset($this->customCompressFlags[$index]);
    }

    return $this;
  }

  /**
   * Defines a callback to be called when the progress changes.
   *
   * @param callable $callback Função de callback para receber o progresso.
   */
  public function setProgressCallback(callable $callback): static
  {
    $this->progressCallback = $callback;

    return $this;
  }
}
