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
    'zip'    => ['tzip'],
    '7z'     => ['t7z', 'm0' => 'lzma2'],
    'lzma2'  => ['t7z', 'm0' => 'lzma2'],
    'lz4'    => ['t7z', 'm0' => 'lz4'],
    'lz5'    => ['t7z', 'm0' => 'lz5'],
    'bz2'    => ['t7z', 'm0' => 'bzip2'],
    'bzip2'  => ['t7z', 'm0' => 'bzip2'],
    'zstd'   => ['t7z', 'm0' => 'zstd'],
    'zst'    => ['t7z', 'm0' => 'zstd'],
    'brotli' => ['t7z', 'm0' => 'brotli'],
    'br'     => ['t7z', 'm0' => 'brotli'],
    'gzip'   => ['t7z', 'm0' => 'gzip'],
    'tar'    => ['ttar'],
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
   * The password to be used for encryption or decryption.
   *
   * @var ?string
   */
  protected ?string $password = null;

  /**
   * Whether or not to encrypt file names.
   *
   * @var bool|null
   */
  protected ?bool $encryptNames = true;

  /**
   * The encryption method to be used for ZIP archives.
   *
   * @var string Can be 'ZipCrypto' (not secure) or 'AES128' or 'AES192' or 'AES256'
   */
  protected string $zipEncryptionMethod = 'AES128';

  /**
   * Constructs a new SevenZip instance.
   *
   * If $sevenZipPath is set, it will be used as the path to the 7-Zip executable.
   * If $sevenZipPath is set to true, it will attempt to automatically detect the 7-Zip executable.
   * If $sevenZipPath is null, it will use package provided 7-Zip executable.
   * If the executable is not found, an ExecutableNotFoundException will be thrown.
   *
   * @param string|bool|null $sevenZipPath Path to the 7-Zip executable file.
   * @throws ExecutableNotFoundException If the 7-Zip executable is not found or not executable
   */
  public function __construct(string|bool|null $sevenZipPath = null)
  {
    if (is_string($sevenZipPath)) {
      // Set the 7-Zip executable path

      if (!file_exists($sevenZipPath)) {
        throw new ExecutableNotFoundException('7-Zip binary not found: ' . $sevenZipPath);
      }
      if (!is_executable($sevenZipPath)) {
        throw new ExecutableNotFoundException('7-Zip binary not executable: ' . $sevenZipPath);
      }

      $this->setSevenZipPath($sevenZipPath);
    }

    if ($sevenZipPath === true) {
      // Try to automatically detect the 7-Zip executable
      $finder       = new ExecutableFinder();
      $sevenZipPath = $finder->find('7z');
      if ($sevenZipPath !== null) {
        $this->setSevenZipPath($sevenZipPath);
      }
    }

    if ($this->getSevenZipPath() === null) {
      $this->setSevenZipPath($this->usePackageProvided7ZipExecutable());
    }


    if ($this->getSevenZipPath() === null) {
      throw new ExecutableNotFoundException();
    }

    // Here some options not set in $alwaysFlags to let the user override it

    // Multi-Threaded Mode ON by default
    $this->mmt();
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
   * Uses the 7-Zip executable provided by the package.
   *
   * @return string|null The path to the 7-Zip executable, or null if the OS or architecture is not supported.
   */
  public function usePackageProvided7ZipExecutable(): ?string
  {
    ### UPDATING BINARIES ###
    // 1. Download from https://www.7-zip.org/download.html
    // 2. Unpack
    // 3. Rename 7zzs (7zz for mac) its parent folder name
    // 4. Move to bin folder here
    // 5. Update $version
    // 6. (Optional, rare) Update $os support

    $version = 2403;

    $os = match (PHP_OS_FAMILY) {
      'Darwin' => 'mac',
      'Linux'  => 'linux',
      default  => null,
    };

    $arch = match (php_uname('m')) {
      'x86_64'           => 'x64',
      'x86'              => 'x86',
      'arm64', 'aarch64' => 'arm64',
      'arm'              => 'arm',
      default            => null,
    };

    if ($os !== null && $arch !== null) {
      return sprintf('%s/../bin/7z%d-%s%s', __DIR__, $version, $os, $os === 'mac' ? '' : '-' . $arch);
    }

    return null;
  }

  /**
   * Set the number of CPU threads to use for compression.
   *
   * @param int|bool|string $threads The number of CPU threads to use, or 'on' or 'off'.
   * @return $this The current instance of the SevenZip class.
   */
  public function mmt(int|bool|string $threads = 'on'): self
  {
    if ($threads === true) {
      $threads = 'on';
    }
    if ($threads === false || $threads === 0 || $threads === '0') {
      $threads = 'off';
    }

    return $this->addFlag('mmt', $threads);
  }

  /**
   * Add a compression flag.
   *
   * @param string $flag The compression flag to be added.
   * @param mixed $value The value for the flag (optional).
   * @return $this The current instance of the SevenZip class.
   */
  public function addFlag(string $flag, $value = null): self
  {
    $customFlags        = $this->getCustomFlags();
    $customFlags[$flag] = $value;
    return $this->setCustomFlags($customFlags);
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
   * Encrypts the data using the provided password.
   *
   * @param string $password The password to encrypt the data.
   * @return self Returns the current instance of this class.
   */
  public function encrypt(string $password): self
  {
    return $this->setPassword($password);
  }

  /**
   * Do not encrypt file names.
   *
   * @return $this
   */
  public function notEncryptNames(): self
  {
    if ($this->getFormat() === 'zip') {
      $this->removeFlag('em');
    } else {
      $this->removeFlag('mhe');
    }

    return $this->setEncryptNames(false);
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
  public function setFormat(string $format): self
  {
    $this->format = $format;
    return $this;
  }

  /**
   * Remove a compression flag.
   *
   * @param string $flag The compression flag to be removed.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function removeFlag(string $flag): self
  {
    $customFlags = $this->getCustomFlags();

    unset($customFlags[$flag]);

    return $this->setCustomFlags($customFlags);
  }

  /**
   * Decrypts the data using the provided password.
   *
   * @param string $password The password to decrypt the data.
   * @return self Returns the current instance of this class.
   */
  public function decrypt(string $password): self
  {
    return $this->setPassword($password);
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
    if ($format) $this->setFormat($format);
    if ($sourcePath) $this->setSourcePath($sourcePath);
    if ($targetPath) $this->setTargetPath($targetPath);

    if (!$this->getTargetPath()) {
      throw new \InvalidArgumentException('Archive file path (target) must be set or passed as argument');
    }

    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException('File or directory path (source) must be set or passed as argument');
    }

    if ($this->getFormat() === 'zip') {
      if (!$this->getFlag('mm')) {
        $this->mm('Deflate64');
      }

      if ($this->getPassword()) {
        $this->addFlag('mem', $this->getZipEncryptionMethod());
      }
    }

    if ($this->getPassword() && $this->getEncryptNames() && $this->getFormat() !== 'zip') {
      $this->addFlag('mhe');
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

    if ($this->getPassword()) {
      $command[] = '-p' . $this->getPassword();
    }

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
  public function setTargetPath(string $path): self
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
  public function setSourcePath(string $path): self
  {
    $this->sourcePath = $path;
    return $this;
  }

  public function getFlag(string $flag): mixed
  {
    return $this->customFlags[$flag] ?? null;
  }

  /**
   * Set the compression method for ZIP format
   *
   * @param string $method Sets a method: Copy, Deflate, Deflate64, BZip2, LZMA, PPMd.
   * @return $this
   */
  public function mm(string $method): self
  {
    return $this->addFlag('mm', $method);
  }

  /**
   * Get the password to be used for encryption or decryption.
   *
   * @return ?string The password or null if not set.
   */
  public function getPassword(): ?string
  {
    return $this->password;
  }

  /**
   * Set the password to be used for encryption or decryption.
   *
   * @param string $password The password to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function setPassword(string $password): self
  {
    $this->password = $password;
    return $this;
  }

  public function getZipEncryptionMethod(): string
  {
    return $this->zipEncryptionMethod;
  }

  public function setZipEncryptionMethod(string $zipEncryptionMethod): SevenZip
  {
    $this->zipEncryptionMethod = $zipEncryptionMethod;
    return $this;
  }

  public function getEncryptNames(): ?bool
  {
    return $this->encryptNames;
  }

  public function setEncryptNames(?bool $encryptNames): SevenZip
  {
    $this->encryptNames = $encryptNames;
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
   * Run a 7z command and parse its output.
   *
   * @param array $command The 7-Zip command to be executed.
   * @return string The output from the 7-Zip command
   * @throws \RuntimeException If the command fails to execute successfully.
   */
  protected function runCommand(array $command, bool $secondary = false): string
  {
    $process = new Process($command);

    $process->run($secondary ? null : function ($type, $buffer) {
      if ($type === Process::OUT) {
        $this->parseProgress($buffer);
      }
    });

    if (!$process->isSuccessful()) {
      throw new \RuntimeException($process->getErrorOutput());
    }

    if (!$secondary) {
      $this->setProgress(100);
      $this->reset();
    }

    return $process->getOutput();
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
  public function setProgressCallback(callable $callback): self
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
  protected function setLastProgress(int $lastProgress): self
  {
    $this->lastProgress = $lastProgress;
    return $this;
  }

  /**
   * Reset the property values to their original state.
   *
   * @return SevenZip The current instance of the SevenZip class.
   */
  public function reset(): self
  {
    $this->customFlags      = [];
    $this->progressCallback = null;
    $this->lastProgress     = -1;
    $this->format           = null;
    $this->targetPath       = '';
    $this->sourcePath       = '';
    $this->password         = null;

    return $this;
  }

  public function info(): void
  {
    echo $this->getInfo();
  }

  public function getInfo()
  {
    return $this->runCommand([$this->getSevenZipPath(), 'i'], secondary: true);
  }

  /**
   * Checks if the given extension(s) are supported by the current 7-Zip installation.
   *
   * @param string|array $extensions The extension or an array of extensions to check.
   * @return bool Returns true if all the given extensions are supported, false otherwise.
   */
  public function checkSupport(string|array $extensions): bool
  {
    $supportedExtensions = $this->getSupportedFormatExtensions();

    if (is_string($extensions)) {
      $extensions = [$extensions];
    }

    foreach ($extensions as $extension) {
      if (!in_array($extension, $supportedExtensions, true)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Get all supported format extensions from the given array.
   *
   * @param array|null $formats The array of format data. If not provided, the built-in info will be used.
   * @return array The array of supported format extensions.
   */
  public function getSupportedFormatExtensions(?array $formats = null): array
  {
    $formats ??= $this->getParsedInfo()['formats'];

    $extensions = [];
    foreach ($formats as $format) {
      foreach ($format['extensions'] as $extension) {
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        if ($extension !== '') {
          $extensions[$extension] = $extension;
        }
      }
    }

    $extensions = array_values($extensions);
    sort($extensions, SORT_STRING | SORT_FLAG_CASE);

    return $extensions;
  }

  public function getParsedInfo(?string $output = null): array
  {
    return $this->parseInfoOutput($output ?? $this->getInfo());
  }

  /**
   * Parses the given output and creates an array with version, formats, codecs, and hashers.
   *
   * @param string $output The output to parse
   * @return array The parsed data
   */
  protected function parseInfoOutput(string $output): array
  {
    $data = [
      'version' => '',
      'formats' => [],
      'codecs'  => [],
      'hashers' => [],
    ];

    $lines = explode("\n", $output);

    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, '7-Zip')) {
        $data['version'] = $line;
      } elseif (str_starts_with($line, 'Formats:')) {
        continue;
      } elseif (str_starts_with($line, 'Codecs:')) {
        break;
      } else {
        $regex = '/(.+?)\s{2}([A-za-z0-9]+)\s+((?:[a-z0-9().~]+\s?)+)(.*)/mu';
        preg_match_all($regex, $line, $matches, PREG_SET_ORDER, 0);
        if (isset($matches[0]) && count($matches[0]) >= 3) {
          $formatParts = array_map('trim', $matches[0]);

          $data['formats'][] = [
            'flags'      => $formatParts[1],
            'name'       => $formatParts[2],
            'extensions' => explode(' ', $formatParts[3]),
            'signature'  => $formatParts[4],
          ];
        }
      }
    }

    $codecsStarted = false;
    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, 'Codecs:')) {
        $codecsStarted = true;
        continue;
      }

      if ($codecsStarted) {
        if (str_starts_with($line, 'Hashers:')) {
          break;
        }

        $codecParts = preg_split('/\s+/', $line);
        if (count($codecParts) >= 2) {
          $data['codecs'][] = [
            'flags' => $codecParts[0],
            'id'    => $codecParts[1],
            'name'  => $codecParts[2],
          ];
        }
      }
    }

    $hashersStarted = false;
    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, 'Hashers:')) {
        $hashersStarted = true;
        continue;
      }

      if ($hashersStarted) {
        $hasherParts = preg_split('/\s+/', $line);
        if (count($hasherParts) >= 3) {
          $data['hashers'][] = [
            'size' => (int)$hasherParts[0],
            'id'   => $hasherParts[1],
            'name' => $hasherParts[2],
          ];
        }
      }
    }

    return $data;
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
    if ($sourcePath) $this->setSourcePath($sourcePath);
    if ($targetPath) $this->setTargetPath($targetPath);

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

    if ($this->getPassword()) {
      $command[] = '-p' . $this->getPassword();
    }

    return $this->runCommand($command);
  }

  /**
   * Sets the source path for the compression or extraction operation.
   *
   * @param string $path The source path.
   * @return static The current instance of the SevenZip class.
   */
  public function source(string $path): self
  {
    return $this->setSourcePath($path);
  }

  /**
   * Set the archive format.
   *
   * @param string $format The compression format to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function format(string $format): self
  {
    return $this->setFormat($format);
  }

  /**
   * Set the progress callback using a fluent interface.
   *
   * @param callable $callback The callback function to be called during the compression progress.
   * @return $this The current instance of the SevenZip class.
   */
  public function progress(callable $callback): self
  {
    return $this->setProgressCallback($callback);
  }

  /**
   * Set the compression level to faster.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function faster(): self
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
  public function mx(int $level): self
  {
    return $this->addFlag('mx', $level);
  }

  /**
   * Set the compression level to slower.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function slower(): self
  {
    if ($this->getFormat() === 'zstd' || $this->getFormat() === 'zst') {
      return $this->mx(22);
    }

    return $this->mx(9);
  }

  /*
   * Sets level of file analysis.
   *
   * @param int $level
   * @return $this
   */

  /**
   * Configures maximum compression settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function ultra(): self
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
  public function mpass(int $number = 7): self
  {
    return $this->addFlag('mpass', $number);
  }

  /**
   * Set the size of the Fast Bytes for the compression algorithm.
   *
   * @param int $bytes The size of the Fast Bytes. The default value (when set) is 64.
   * @return $this The current instance of the SevenZip class.
   */
  public function mfb(int $bytes = 64): self
  {
    return $this->addFlag('mfb', $bytes);
  }

  public function md(string $size = '32m'): self
  {
    return $this->addFlag('md', $size);
  }

  public function ms(bool|string|int $on = true): self
  {
    return $this->addFlag('ms', $on ? 'on' : 'off');
  }

  /**
   * Set the compression method.
   * @param $method string The compression method to be used.
   * @return $this
   */
  public function m0($method): self
  {
    return $this->addFlag('m0', $method);
  }

  /**
   * Configures no compression (copy only) settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function copy(): self
  {
    return $this->mmt(true)->mx(0)->m0('Copy')->mm('Copy')->myx(0);
  }

  /**
   * Sets file analysis level.
   *
   * @param int $level
   * @return $this
   */
  public function myx(int $level = 5): self
  {
    return $this->addFlag('myx', $level);
  }

  /**
   * Set the target path for compression/extraction using a fluent interface.
   *
   * @param string|null $path The path to the target file or directory for compression or extraction.
   * @return $this The current instance of the SevenZip class.
   */
  public function target(?string $path): self
  {
    return $this->setTargetPath($path);
  }
}
