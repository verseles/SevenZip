<?php

namespace Verseles\SevenZip;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Verseles\SevenZip\Exceptions\ExecutableNotFoundException;

class SevenZip
{
  /**
   * Path to the 7-Zip executable file. If not set, it will be automatically detected.
   * @var ?string
   */
  protected ?string $sevenZipPath = null;

  /**
   * Array of flags that are always used when running 7-Zip commands.
   * These flags are used to suppress progress output and automatically confirm operations.
   * @var array
   */
  protected array $alwaysFlags = ['bsp1', 'y' => null];

  /**
   * Default compression flags for different formats.
   * Each format has a set of specific flags that are used to optimize the compression for that format.
   * @var array
   */
  protected array $defaultCompressFlags = [
    'zip'   => ['tzip'],
    '7z'    => ['t7z', 'm0' => 'lzma2'],
    'lzma2' => ['t7z', 'm0' => 'lzma2'],
    'lz4'   => ['t7z', 'm0' => 'lz4'],
    'lz5'   => ['t7z', 'm0' => 'lz5'],
    'bz2'   => ['t7z', 'm0' => 'bzip2'],
    'bzip2' => ['t7z', 'm0' => 'bzip2'],
    'zstd'  => ['t7z', 'm0' => 'zstd'],
    'zst'   => ['t7z', 'm0' => 'zstd'],
    'tar'   => ['ttar'],
  ];

  /**
   * Custom flags that can be added to the 7-Zip command.
   * These flags allow for further customization of the compression/extraction process.
   * @var array
   */
  protected array $customFlags = [];

  /**
   * Callback function to be called during the compression progress.
   * This function will be called with the current progress percentage as an argument.
   * @var ?callable
   */
  protected $progressCallback;

  /**
   * The last reported progress percentage.
   * @var int
   */
  protected int $lastProgress = -1;

  /**
   * The compression format to be used.
   * @var ?string
   */
  protected ?string $format;

  /**
   * The path to the target file or directory for compression or extraction.
   * @var string
   */
  protected string $targetPath;

  /**
   * The path to the source file or directory for compression or extraction.
   * @var string
   */
  protected string $sourcePath;

  /**
   * Constructs a new SevenZip instance.
   * If $sevenZipPath is not provided, it will attempt to automatically detect the 7-Zip executable.
   * If the executable is not found, an ExecutableNotFoundException will be thrown.
   *
   * @param ?string $sevenZipPath Path to the 7-Zip executable file.
   * @throws ExecutableNotFoundException If the 7-Zip executable is not found.
   */
  public function __construct(?string $sevenZipPath = null)
  {
    if ($sevenZipPath !== null) {
      $this->setSevenZipPath($sevenZipPath);
    }

    if ($this->getSevenZipPath() === null) {
      $finder       = new ExecutableFinder();
      $sevenZipPath = $finder->find('7z');
      $this->setSevenZipPath($sevenZipPath);
    }

    if ($this->getSevenZipPath() === null) {
      throw new ExecutableNotFoundException();
    }
  }

  /**
   * Gets the path to the 7-Zip executable file.
   *
   * @return ?string Path to the 7-Zip executable file.
   */
  public function getSevenZipPath(): ?string
  {
    return $this->sevenZipPath;
  }

  /**
   * Sets the path to the 7-Zip executable file.
   *
   * @param string $sevenZipPath Path to the 7-Zip executable file.
   * @return SevenZip The current instance of the SevenZip class.
   */
  public function setSevenZipPath(string $sevenZipPath): SevenZip
  {
    $this->sevenZipPath = $sevenZipPath;
    return $this;
  }

  /**
   * Sets the source path for the compression or extraction operation.
   *
   * @param string $path The source path.
   * @return static The current instance of the SevenZip class.
   */
  public function source(string $path): static
  {
    return $this->setSourcePath($path);
  }

  /**
   * Set the archive format.
   *
   * @param string $format The compression format to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function format(string $format): static
  {
    return $this->setFormat($format);
  }

  /**
   * Compress a file or directory.
   *
   * @param ?string $format Archive format (optional).
   * @param ?string $sourcePath Path to the file or directory to compress (optional).
   * @param ?string $targetPath Path to the compressed archive (optional).
   * @return bool True on success.
   * @throws \InvalidArgumentException If format, target path, or source path is not set.
   *
   */
  public function compress(?string $format = null, ?string $sourcePath = null, ?string $targetPath = null): bool
  {
    if ($format !== null) {
      $this->setFormat($format);
    }

    if ($sourcePath !== null) {
      $this->setSourcePath($sourcePath);
    }

    if ($targetPath !== null) {
      $this->setTargetPath($targetPath);
    }

    if (!$this->getTargetPath()) {
      throw new \InvalidArgumentException('Archive file path (target) must be set or passed as argument');
    }

    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException('File or directory path (source) must be set or passed as argument');
    }

    $command = [
      $this->sevenZipPath,
      'a',
      ...$this->flagrize($this->getAlwaysFlags()),
      ...$this->flagrize($this->getDefaultCompressFlags()),
      ...$this->flagrize($this->getCustomFlags()),
      $this->getTargetPath(),
      $this->getSourcePath(),
    ];

    return $this->runCommand($command);
  }

  /**
   * Get the target path for compression/extraction.
   *
   * @return string The path to the target file or directory for compression or extraction.
   */
  public function getTargetPath(): string
  {
    return $this->targetPath;
  }

  /**
   * Set the target path for compression/extraction.
   *
   * @param string $path The path to the target file or directory for compression or extraction.
   * @return $this The current instance of the SevenZip class.
   */
  public function setTargetPath(string $path): static
  {
    $this->targetPath = $path;
    return $this;
  }

  /**
   * Get the source path for compression/extraction.
   *
   * @return string The path to the source file or directory for compression or extraction.
   */
  public function getSourcePath(): string
  {
    return $this->sourcePath;
  }

  /**
   * Set the source path for compression/extraction.
   *
   * @param string $path The path to the source file or directory for compression or extraction.
   * @return $this The current instance of the SevenZip class.
   */
  public function setSourcePath(string $path): static
  {
    $this->sourcePath = $path;
    return $this;
  }

  /**
   * Format flags and values into an array of strings suitable for passing to 7-Zip commands.
   *
   * @param array $flagsAndValues An associative array of flags and their corresponding values.
   *                              If the value is null, the flag will be added without an equal sign.
   * @return array An array of formatted flag strings.
   */
  public function flagrize(array $flagsAndValues): array
  {
    $formattedFlags = [];

    foreach ($flagsAndValues as $flag => $value) {
      if (is_numeric($flag)) {
        $flag  = $value;
        $value = null;
      }

      $formattedFlag = '-' . $flag;

      if ($value !== null) {
        $formattedFlag .= '=' . $value;
      }

      $formattedFlags[] = $formattedFlag;
    }

    return $formattedFlags;
  }

  /**
   * Get the always flags.
   *
   * @return array The array of flags that are always used when running 7-Zip commands.
   */
  protected function getAlwaysFlags(): array
  {
    return $this->alwaysFlags;
  }

  /**
   * Set the always flags.
   *
   * @param array $alwaysFlags The array of flags that are always used when running 7-Zip commands.
   * @return SevenZip The current instance of the SevenZip class.
   */
  protected function setAlwaysFlags(array $alwaysFlags): SevenZip
  {
    $this->alwaysFlags = $alwaysFlags;
    return $this;
  }

  /**
   * Get the default compression flags for the specified format.
   *
   * @param string|null $format Archive format (optional).
   * @return array The default compression flags for the specified format.
   */
  protected function getDefaultCompressFlags(?string $format = null): array
  {
    if ($format !== null) {
      $this->setFormat($format);
    }

    return $this->defaultCompressFlags[$this->getFormat()] ?? ['t' . $this->getFormat()];
  }

  /**
   * Get the archive format.
   *
   * @return string The compression format to be used.
   */
  public function getFormat(): string
  {
    return $this->format ?? '7z';
  }

  /**
   * Set the archive format.
   *
   * @param string $format The compression format to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function setFormat(string $format): static
  {
    $this->format = $format;
    return $this;
  }

  /**
   * Get the custom compression flags.
   *
   * @return array The custom compression flags that have been added.
   */
  public function getCustomFlags(): array
  {
    return $this->customFlags;
  }

  /**
   * Set the custom compression flags.
   *
   * @param array $customFlags The custom compression flags to be used.
   * @return SevenZip The current instance of the SevenZip class.
   */
  public function setCustomFlags(array $customFlags): SevenZip
  {
    $this->customFlags = $customFlags;
    return $this;
  }

  /**
   * Run a 7z command and parse its output.
   *
   * @param array $command The 7-Zip command to be executed.
   * @return bool True if the command was successful
   * @throws \RuntimeException If the command fails to execute successfully.
   */
  protected function runCommand(array $command): bool
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

    $this->setProgress(100);

    $this->reset();

    return true;
  }

  /**
   * Parse the progress from the command output.
   *
   * @param string $output The output from the 7-Zip command.
   */
  protected function parseProgress(string $output): void
  {
    if ($this->getProgressCallback() === null) {
      return;
    }

    $lines = explode("\n", $output);
    foreach ($lines as $line) {
      if (preg_match('/(\d+)%\s+\d+/', $line, $matches)) {
        $progress = intval($matches[1]);
        $this->setProgress($progress);
      }
    }
  }

  /**
   * Get the progress callback.
   *
   * @return callable|null The callback function to be called during the compression progress.
   */
  public function getProgressCallback(): ?callable
  {
    return $this->progressCallback;
  }

  /**
   * Set the progress callback.
   *
   * @param callable $callback The callback function to be called during the compression progress.
   * @return $this The current instance of the SevenZip class.
   */
  public function setProgressCallback(callable $callback): static
  {
    $this->progressCallback = $callback;
    return $this;
  }

  protected function setProgress(int $progress): void
  {
    if ($this->getProgressCallback() !== null && $progress > $this->getLastProgress()) {
      $this->setLastProgress($progress);
      call_user_func($this->getProgressCallback(), $progress);
    }
  }

  /**
   * Get the last reported progress.
   *
   * @return int The last reported progress percentage.
   */
  public function getLastProgress(): int
  {
    return $this->lastProgress;
  }

  /**
   * Set the last reported progress.
   *
   * @param int $lastProgress The last reported progress percentage.
   * @return SevenZip The current instance of the SevenZip class.
   */
  protected function setLastProgress(int $lastProgress): SevenZip
  {
    $this->lastProgress = $lastProgress;
    return $this;
  }

  /**
   * Reset the property values to their original state.
   *
   * @return SevenZip The current instance of the SevenZip class.
   */
  public function reset(): SevenZip
  {
    $this->customFlags      = [];
    $this->progressCallback = null;
    $this->lastProgress     = -1;
    $this->format           = null;
    $this->targetPath       = '';
    $this->sourcePath       = '';

    return $this;
  }

  /**
   * Set the progress callback using a fluent interface.
   *
   * @param callable $callback The callback function to be called during the compression progress.
   * @return $this The current instance of the SevenZip class.
   */
  public function progress(callable $callback): static
  {
    return $this->setProgressCallback($callback);
  }

  /**
   * Set the compression level to faster.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function faster(): static
  {
    if ($this->getFormat() === 'zstd' || $this->getFormat() === 'zst') {
      return $this->mx(0);
    }

    return $this->mx(1);
  }

  /**
   * Set the compression level using the -mx flag.
   *
   * @param int $level The compression level to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function mx(int $level): static
  {
    return $this->addFlag('mx', $level);
  }

  /**
   * Add a compression flag.
   *
   * @param string $flag The compression flag to be added.
   * @param mixed $value The value for the flag (optional).
   * @return $this The current instance of the SevenZip class.
   */
  public function addFlag(string $flag, $value = null): static
  {
    $customFlags        = $this->getCustomFlags();
    $customFlags[$flag] = $value;
    return $this->setCustomFlags($customFlags);
  }

  /**
   * Set the compression level to slower.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function slower(): static
  {
    if ($this->getFormat() === 'zstd' || $this->getFormat() === 'zst') {
      return $this->mx(22);
    }

    return $this->mx(9);
  }

  /**
   * Configures maximum compression settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function ultra(): static
  {
    $this->mmt(true)->mx(9);

    return match ($this->getFormat()) {
      'zip'         => $this->mm('Deflate64')->mfb(257)->mpass(15)->mmem(28),
      'gzip'        => $this->mfb(258)->mpass(15),
      'bzip2'       => $this->mpass(7)->md('900000b'),
      '7z'          => $this->m0('lzma2')->mfb(64)->ms(true)->md('32m'),
      'zstd', 'zst' => $this->mx(22),
      default       => $this,
    };
  }

  /**
   * Set the number of CPU threads to use for compression.
   *
   * @param int|bool|string $threads The number of CPU threads to use, or 'on' or 'off'.
   * @return $this The current instance of the SevenZip class.
   */
  public function mmt(int|bool|string $threads = 'on'): static
  {
    if ($threads === true) {
      $threads = 'on';
    }
    if ($threads === false || $threads === 0 || $threads === '0') {
      $threads = 'off';
    }

    return $this->addFlag('mmt', $threads);
  }

  public function mmem(int|string $size = 24)
  {
    return $this->addFlag('mmem', $size);
  }

  /**
   * Set the number of passes for compression.
   *
   * @param int $number The number of passes for compression.
   * @return $this The current instance of the SevenZip class.
   */
  public function mpass(int $number = 7): static
  {
    return $this->addFlag('mpass', $number);
  }

  /**
   * Set the size of the Fast Bytes for the compression algorithm.
   *
   * @param int $bytes The size of the Fast Bytes. The default value (when set) is 64.
   * @return $this The current instance of the SevenZip class.
   */
  public function mfb(int $bytes = 64): static
  {
    return $this->addFlag('mfb', $bytes);
  }

  /**
   * Set the compression method for ZIP format
   *
   * @param string $method Sets a method: Copy, Deflate, Deflate64, BZip2, LZMA, PPMd.
   * @return $this
   */
  public function mm(string $method = 'Deflate'): static
  {
    return $this->addFlag('mm', $method);
  }

  public function md(string $size = '32m'): static
  {
    return $this->addFlag('md', $size);
  }

  public function ms(bool|string|int $on = true): static
  {
    return $this->addFlag('ms', $on ? 'on' : 'off');
  }

  /**
   * Set the compression method.
   * @param $method string The compression method to be used.
   * @return $this
   */
  public function m0($method): static
  {
    return $this->addFlag('m0', $method);
  }

  /*
   * Sets level of file analysis.
   *
   * @param int $level
   * @return $this
   */

  /**
   * Configures no compression (copy only) settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function copy(): static
  {
    return $this->mmt(true)->mx(0)->m0('Copy')->mm('Copy')->myx(0);
  }

  /**
   * Sets file analysis level.
   *
   * @param int $level
   * @return $this
   */
  public function myx(int $level = 5): static
  {
    return $this->addFlag('myx', $level);
  }

  /**
   * Extract an archive.
   *
   * @param ?string $sourcePath Path to the archive (optional).
   * @param ?string $targetPath Path to extract the archive (optional).
   * @return bool True on success.
   * @throws \InvalidArgumentException If source path or target path is not set.
   *
   */
  public function extract(?string $sourcePath = null, ?string $targetPath = null): bool
  {
    if ($sourcePath !== null) {
      $this->setSourcePath($sourcePath);
    }

    if ($targetPath !== null) {
      $this->setTargetPath($targetPath);
    }

    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException('Archive path (source) must be set or passed as argument');
    }

    if (!$this->getTargetPath()) {
      throw new \InvalidArgumentException('Extract path (target) must be set or passed as argument');
    }

    $command = [
      $this->getSevenZipPath(),
      'x',
      ...$this->flagrize($this->getAlwaysFlags()),
      ...$this->flagrize($this->getCustomFlags()),
      $this->getSourcePath(),
      '-o' . $this->getTargetPath(),
    ];

    return $this->runCommand($command);
  }

  /**
   * Set the target path for compression/extraction using a fluent interface.
   *
   * @param string|null $path The path to the target file or directory for compression or extraction.
   * @return $this The current instance of the SevenZip class.
   */
  public function target(?string $path): static
  {
    return $this->setTargetPath($path);
  }

  /**
   * Remove a compression flag.
   *
   * @param string $flag The compression flag to be removed.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function removeFlag(string $flag): static
  {
    $customFlags = $this->getCustomFlags();

    unset($customFlags[$flag]);

    return $this->setCustomFlags($customFlags);
  }
}
