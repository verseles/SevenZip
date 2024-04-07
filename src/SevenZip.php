<?php

namespace Verseles\SevenZip;

use Exception;
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
  protected array $alwaysFlags = [
    "bsp1", // Show progress on stdout
    "y", // Auto confirm
    "sccUTF-8", // UTF-8
    //    "ssp", // Do not modify 'Last Access Time' property of source files when archiving or hashing
  ];

  /**
   * Default compression flags for different formats.
   * @var array
   */
  protected array $formatFlags = ["t7z", "m0" => "lzma2"];

  /**
   * Force use of tar before compressing.
   * @var bool
   */
  protected bool $forceTarBefore = false;

  /**
   * Whether to keep file permissions and attributes when creating a tar archive.
   * @var bool
   */
  protected bool $keepFileInfoOnTar = true;

  /**
   * Whether the archive was already tarred.
   * @var bool
   */
  protected bool $alreadyTarred = false;

  /**
   * When decompressing, whether to automatically untar the archive.
   * @var bool
   */
  protected bool $autoUntar = true;

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

  protected int $divideProgressBy = 1;

  /**
   * The compression format to be used.
   * @var ?string
   */
  protected ?string $format;

  /**
   * The path to the target file or directory for compression or extraction.
   * @var string
   */
  protected ?string $targetPath = null;

  /**
   * The path to the source file or directory for compression or extraction.
   * @var string
   */
  protected ?string $sourcePath = null;

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

  protected int $timeout = 300;

  protected int $idleTimeout = 120;

  protected bool $deleteSourceAfterExtract = false;

  /**
   * The encryption method to be used for ZIP archives.
   *
   * @var string Can be 'ZipCrypto' (not secure) or 'AES128' or 'AES192' or 'AES256'
   */
  protected string $zipEncryptionMethod = "AES256";

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
        throw new ExecutableNotFoundException(
          "7-Zip binary not found: " . $sevenZipPath
        );
      }
      if (!is_executable($sevenZipPath)) {
        throw new ExecutableNotFoundException(
          "7-Zip binary not executable: " . $sevenZipPath
        );
      }

      $this->setSevenZipPath($sevenZipPath);
    }

    if ($sevenZipPath === true) {
      // Try to automatically detect the 7-Zip executable
      $finder       = new ExecutableFinder();
      $sevenZipPath = $finder->find("7z");
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

    // Here some options not set by default to let the user override it

    // Load 7z as default archive format
    $this->format("7z");

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
      "Darwin" => "mac",
      "Linux"  => "linux",
      default  => null,
    };

    $arch = match (php_uname("m")) {
      "x86_64"           => "x64",
      "x86"              => "x86",
      "arm64", "aarch64" => "arm64",
      "arm"              => "arm",
      default            => null,
    };

    if ($os !== null && $arch !== null) {
      $path = sprintf(
        "%s/../bin/7z%d-%s%s",
        __DIR__,
        $version,
        $os,
        $os === "mac" ? "" : "-" . $arch
      );

      $path = realpath($path);

      if (is_executable($path)) {
        return $path;
      }
    }

    return null;
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
   * Set the number of CPU threads to use for compression.
   *
   * @param int|bool|string $threads The number of CPU threads to use, or 'on' or 'off'.
   * @return $this The current instance of the SevenZip class.
   */
  public function mmt(int|bool|string $threads = "on"): self
  {
    if ($threads === true) {
      $threads = "on";
    }
    if ($threads === false || $threads === 0 || $threads === "0") {
      $threads = "off";
    }

    return $this->addFlag("mmt", $threads);
  }

  /**
   * Add custom flags.
   *
   * @param string $flag The compression flag to be added.
   * @param mixed $value The value for the flag (optional).
   * @param bool $glued Whether the flag should be glued with the value instead use = between flag and value (optional).
   * @return $this The current instance of the SevenZip class.
   */
  public function addFlag(
    string $flag,
    string $value = null,
    bool   $glued = false
  ): self
  {
    if ($glued && $value !== null) {
      $flag  .= $value;
      $value = null;
    }

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
    if ($this->getFormat() === "zip") {
      $this->removeFlag("em");
    } else {
      $this->removeFlag("mhe");
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
    return $this->format ?? "7z";
  }

  /**
   * Set the archive format.
   *
   * @param ?string $format The compression format to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function setFormat(?string $format = null): self
  {
    $this->format = $format ?? "7z";

    return $this->setFormatFlags();
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
   * Exclude archive filenames from the current command/archive.
   *
   * @param string|array $fileRefs File references to exclude. Can be a wildcard pattern or a list file.
   * @param bool|int $recursive Recurse type. Can be: true for 'r' (enabled), false for 'r-' (disabled), 0 for 'r0' (enabled for wildcards)
   * @return $this
   *
   * @throws \InvalidArgumentException If the file reference is not a string or an array.
   *
   * @example
   * $sevenZip->exclude('*.7z');
   * $sevenZip->exclude('exclude_list.txt', false);
   * $sevenZip->exclude(['*.7z', '*.zip'], 0);
   */
  public function exclude(
    string|array $fileRefs,
    bool|int     $recursive = true
  ): self
  {
    return $this->includeOrExclude("x", $fileRefs, $recursive);
  }

  /**
   * Add file references flag to the current command/archive.
   *
   * @param string $flag The flag prefix ('x' for exclude, 'i' for include).
   * @param string|array $fileRefs File references to add. Can be a wildcard pattern or a list file.
   * @param bool|int $recursive Recurse type. Can be: true for 'r' (enabled), false for 'r-' (disabled), 0 for 'r0' (enabled for wildcards)
   * @return $this
   *
   * @throws \InvalidArgumentException If the file reference is not a string or an array.
   */
  protected function includeOrExclude(
    string       $flag,
    string|array $fileRefs,
    bool|int     $recursive
  ): self
  {
    if (is_string($fileRefs)) {
      $fileRefs = [$fileRefs];
    }

    $r = match ($recursive) {
      0     => "r0",
      true  => "r",
      false => "r-",
    };

    foreach ($fileRefs as $fileRef) {
      $t = file_exists($fileRef) ? "@" : "!";

      $this->addFlag(flag: $flag . $r . $t, value: $fileRef, glued: true);
    }

    return $this;
  }

  /**
   * Include archive filenames for the current command/archive.
   *
   * @param string|array $fileRefs File references to include. Can be a wildcard pattern or a list file.
   * @param bool|int $recursive Recurse type. Can be: true for 'r' (enabled), false for 'r-' (disabled), 0 for 'r0' (enabled for wildcards)
   * @return $this
   *
   * @throws \InvalidArgumentException If the file reference is not a string or an array.
   *
   * @example
   * $sevenZip->include('*.txt');
   * $sevenZip->include('include_list.txt', false);
   * $sevenZip->include(['*.txt', '*.doc'], 0);
   */
  public function include(
    string|array $fileRefs,
    bool|int     $recursive = true
  ): self
  {
    return $this->includeOrExclude("i", $fileRefs, $recursive);
  }

  /**
   * Prints information about the 7-Zip executable.
   *
   * @return void
   */
  public function info(): void
  {
    print $this->getInfo();
  }

  /**
   * Retrieves information about the 7-Zip executable.
   *
   * @return string The output of the command execution.
   */
  public function getInfo(): string
  {
    return $this->runCommand([$this->getSevenZipPath(), "i"], secondary: true);
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

    echo "\nCommand: " . implode(" ", $command) . "\n";
    $process = new Process($command);
    $process->setTimeout($this->getTimeout());

    $process->run(
      $secondary
        ? null
        : function ($type, $buffer) use ($process) {
        if ($type === Process::OUT) {
          $this->parseProgress($buffer);
        }

        $process->checkTimeout();
      }
    );

    if (!$process->isSuccessful()) {
      throw new \RuntimeException(
        "Command: " . implode(" ", $command) . "\n" .
        "Output: " . $process->getOutput() . "\n" .
        "Error Output: " .
        $process->getErrorOutput());
    }

    if (!$secondary && $this->getDivideProgressBy() === 1) {
      $this->setProgress(100);
      $this->reset();
    }

    return $process->getOutput();
  }

  public function getTimeout(): int
  {
    return $this->timeout;
  }

  public function setTimeout(int $timeout): SevenZip
  {
    $this->timeout = $timeout;
    return $this;
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
      if (preg_match("/(\d+)%\s+\d+/", $line, $matches)) {
        $progress = intval($matches[1]);
        $progress = floor($progress / $this->getDivideProgressBy());

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
   * @param ?callable $callback The callback function to be called during the compression progress.
   * @return $this The current instance of the SevenZip class.
   */
  public function setProgressCallback(?callable $callback): self
  {
    $this->progressCallback = $callback;
    return $this;
  }

  public function getDivideProgressBy(): int
  {
    return $this->divideProgressBy;
  }

  public function setDivideProgressBy(int $number = 1): SevenZip
  {
    $this->divideProgressBy = is_int($number) && $number > 0 ? $number : 1;
    return $this;
  }

  protected function setProgress(int $progress): void
  {
    if (
      $this->getProgressCallback() !== null &&
      $progress > $this->getLastProgress()
    ) {
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
   * @param int $value The last reported progress percentage.
   * @return SevenZip The current instance of the SevenZip class.
   */
  protected function setLastProgress(int $value): self
  {
    $this->lastProgress = $value;
    return $this;
  }

  /**
   * Reset the property values to their original state.
   *
   * @return SevenZip The current instance of the SevenZip class.
   */
  public function reset(): self
  {
    $this->customFlags              = [];
    $this->progressCallback         = null;
    $this->lastProgress             = -1;
    $this->format                   = null;
    $this->targetPath               = null;
    $this->sourcePath               = null;
    $this->password                 = null;
    $this->encryptNames             = true;
    $this->timeout                  = 300;
    $this->idleTimeout              = 120;
    $this->zipEncryptionMethod      = 'AES256';
    $this->forceTarBefore           = false;
    $this->keepFileInfoOnTar        = true;
    $this->alreadyTarred            = false;
    $this->autoUntar                = true;
    $this->deleteSourceAfterExtract = false;

    return $this;
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
    $formats ??= $this->getParsedInfo()["formats"];

    $extensions = [];
    foreach ($formats as $format) {
      foreach ($format["extensions"] as $extension) {
        $extension = preg_replace("/[^a-zA-Z0-9]/", "", $extension);
        if ($extension !== "") {
          $extensions[$extension] = $extension;
        }
      }
    }

    $extensions = array_values($extensions);
    sort($extensions, SORT_STRING | SORT_FLAG_CASE);

    return $extensions;
  }

  protected function getParsedInfo(?string $output = null): array
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
      "version" => "",
      "formats" => [],
      "codecs"  => [],
      "hashers" => [],
    ];

    $lines = explode("\n", $output);

    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, "7-Zip")) {
        $data["version"] = $line;
      } elseif (str_starts_with($line, "Formats:")) {
        continue;
      } elseif (str_starts_with($line, "Codecs:")) {
        break;
      } else {
        $regex = "/(.+?)\s{2}([A-za-z0-9]+)\s+((?:[a-z0-9().~]+\s?)+)(.*)/mu";
        preg_match_all($regex, $line, $matches, PREG_SET_ORDER, 0);
        if (isset($matches[0]) && count($matches[0]) >= 3) {
          $formatParts = array_map("trim", $matches[0]);

          $data["formats"][] = [
            "flags"      => $formatParts[1],
            "name"       => $formatParts[2],
            "extensions" => explode(" ", $formatParts[3]),
            "signature"  => $formatParts[4],
          ];
        }
      }
    }

    $codecsStarted = false;
    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, "Codecs:")) {
        $codecsStarted = true;
        continue;
      }

      if ($codecsStarted) {
        if (str_starts_with($line, "Hashers:")) {
          break;
        }

        $codecParts = preg_split("/\s+/", $line);
        if (count($codecParts) >= 2) {
          $data["codecs"][] = [
            "flags" => $codecParts[0],
            "id"    => $codecParts[1],
            "name"  => $codecParts[2],
          ];
        }
      }
    }

    $hashersStarted = false;
    foreach ($lines as $line) {
      $line = trim($line);

      if (str_starts_with($line, "Hashers:")) {
        $hashersStarted = true;
        continue;
      }

      if ($hashersStarted) {
        $hasherParts = preg_split("/\s+/", $line);
        if (count($hasherParts) >= 3) {
          $data["hashers"][] = [
            "size" => (int)$hasherParts[0],
            "id"   => $hasherParts[1],
            "name" => $hasherParts[2],
          ];
        }
      }
    }

    return $data;
  }

  public function tarBefore(bool $keepFileInfo = true): self
  {
    return $this
      ->forceTarBefore(true)
      ->keepFileInfoOnTar($keepFileInfo);
  }

  /**
   * Set whether to keep file info when using TAR before compression.
   *
   * @param bool $keep Whether to keep file info when using TAR before compression.
   * @return $this The current instance of the SevenZip class.
   */
  public function keepFileInfoOnTar(bool $keep): SevenZip
  {
    $this->keepFileInfoOnTar = $keep;
    return $this;
  }

  /**
   * Set whether to force TAR before compression.
   *
   * @param bool $force Whether to force TAR before compression.
   * @return $this The current instance of the SevenZip class.
   */
  public function forceTarBefore(bool $force): SevenZip
  {
    $this->forceTarBefore = $force;
    return $this;
  }

  /**
   * Extract an archive.
   *
   * @return string The output of the 7-Zip command.
   * @throws \InvalidArgumentException If source path or target path is not set.
   *
   */
  public function extract(): string
  {
    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException("Archive path (source) must be set");
    }

    if (!$this->getTargetPath()) {
      throw new \InvalidArgumentException("Extract path (target) must be set");
    }

    // Set output path
    $this->addFlag("o", $this->getTargetPath(), glued: true);

    if ($this->getPassword()) {
      $this->addFlag("p", $this->getPassword(), glued: true);
    }

    $forceUntar = false;
    if ($this->shouldAutoUntar()) {
      $fileList = $this->fileList();

      $tarFile      = $fileList[0]['path'];
      $isTarFile    = pathinfo($tarFile, PATHINFO_EXTENSION) === 'tar';
      $isSingleFile = count($fileList) === 1;

      $forceUntar = $isSingleFile && $isTarFile;
    }

    $command = [
      $this->getSevenZipPath(),
      "x",
      ...$this->flagrize($this->getAlwaysFlags()),
      ...$this->flagrize($this->getCustomFlags()),
      $this->getSourcePath(),
    ];

    $shouldDeleteSourceAfterExtract = $this->shouldDeleteSourceAfterExtract();
    $sourcePath                     = $this->getSourcePath();


    if ($forceUntar) {
      $output = $this->executeUntarAfter($tarFile, $command);
    } else {
      $output = $this->runCommand($command);
    }

    if ($shouldDeleteSourceAfterExtract) {
      @unlink($sourcePath);
    }

    return $output;
  }

  /**
   * Get the source path for compression/extraction.
   *
   * @return ?string The path to the source file or directory for compression or extraction.
   */
  public function getSourcePath(): ?string
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

  /**
   * Get the target path for compression/extraction.
   *
   * @return ?string The path to the target file or directory for compression or extraction.
   */
  public function getTargetPath(): ?string
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
   * Get the password to be used for encryption or decryption.
   *
   * @return ?string The password or null if not set.
   */
  public function getPassword(): ?string
  {
    return $this->password;
  }

  /**
   * Set the password for encryption or decryption.
   *
   * @param string $password The password to be used for encryption or decryption.
   * @return $this The current instance of the SevenZip class.
   */
  public function setPassword(string $password): self
  {
    $this->password = $password;
    return $this;
  }

  public function shouldAutoUntar(): bool
  {
    return $this->autoUntar;
  }

  /**
   * List the files inside an archive.
   *
   * @return array The list of files inside the archive.
   * @throws \InvalidArgumentException If the source path is not set.
   */
  public function fileList(): array
  {
    return $this->fileInfo()['files'] ?? [];
  }

  /**
   * Get information about an archive and its contents.
   *
   * @return array An array containing 'info' and 'files' keys with the archive information and file list.
   * @throws \InvalidArgumentException If the source path is not set.
   */
  public function fileInfo(): array
  {
    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException('Archive path (source) must be set');
    }

    if ($this->getPassword()) {
      $this->addFlag('p', $this->getPassword(), glued: true);
    }

    $command = [
      $this->getSevenZipPath(),
      'l',
      ...$this->flagrize($this->getAlwaysFlags()),
      ...$this->flagrize($this->getCustomFlags()),
      $this->getSourcePath(),
    ];

    $output = $this->runCommand($command, secondary: true);

    return $this->parseFileInfoOutput($output);
  }

  /**
   * Format flags and values into an array of strings suitable for passing to 7-Zip commands.
   *
   * @param array $array An associative array of flags and their corresponding values.
   *                              If the value is null, the flag will be added without an equal sign.
   * @return array An array of formatted flag strings.
   */
  public function flagrize(array $array): array
  {
    $formattedFlags = [];

    foreach ($array as $flag => $value) {
      if (is_numeric($flag)) {
        // flag with no value
        $flag  = $value;
        $value = null;
      }

      $formattedFlag = "-" . $flag;

      if ($value !== null) {
        $formattedFlag .= "=" . $value;
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
   * Parse the output of the 7z "l" command and return an array with archive information and file list.
   *
   * @param string $output The output of the 7z "l" command.
   * @return array An array containing 'info' and 'files' keys with the archive information and file list.
   */
  protected function parseFileInfoOutput(string $output): array
  {
    $info  = [];
    $files = [];

    $lines = explode("\n", $output);
    $part  = '';

//    var_dump($lines);

    foreach ($lines as $line) {
      if ($part === '' && preg_match('/^--$/', $line)) {
        $part = 'header';
        continue;
      } elseif ($part === 'header' && preg_match('/Date\s+?Time\s+?Attr\s+?Size\s+?Compressed\s+?Name/', $line)) {
        $part = 'files-jump1';
        continue;
      } elseif ($part === 'files-jump1') {
        $part = 'files';
        continue;
      } elseif ($part === 'files' && preg_match('/-{5,}/', $line)) {
        $part = 'total';
        continue;
      }

      if ($part === 'header') {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
          $info[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
      } elseif ($part === 'files') {
        $parts = preg_split('/\s+/', $line, 6);

        if (count($parts) >= 6) {
          $files[] = [
            'date'       => trim($parts[0]),
            'time'       => trim($parts[1]),
            'attr'       => trim($parts[2]),
            'size'       => (int)$parts[3],
            'compressed' => (int)$parts[4],
            'path'       => trim($parts[5]),
          ];
        }
      } elseif ($part === 'total') {
        $info['total']['raw'] = $line;
        $parts                = preg_split('/\s+/', $line, 5);

        $info['total']['date']       = trim($parts[0]);
        $info['total']['time']       = trim($parts[1]);
        $info['total']['size']       = (int)$parts[2];
        $info['total']['compressed'] = (int)$parts[3];
        $files_and_folders           = trim($parts[4]);
        $info['total']['files']      = 0;
        $info['total']['folders']    = 0;

        if (preg_match('/(\d+)\sfiles/', $files_and_folders, $matches)) {
          $info['total']['files'] = (int)$matches[1];
        }

        if (preg_match('/(\d+)\sfolders/', $files_and_folders, $matches)) {
          $info['total']['folders'] = (int)$matches[1];
        }

        $part = 'discard';
      }
    }

    return [
      'info'  => $info,
      'files' => $files,
    ];
  }

  public function shouldDeleteSourceAfterExtract(): bool
  {
    return $this->deleteSourceAfterExtract;
  }

  /**
   * Untar extracted tar file after extracted original archive then delete tar file
   *
   * @param string $tarFile
   * @param array $extractCommand
   * @return string
   */
  public function executeUntarAfter(string $tarFile, array $extractCommand): string
  {
    $this->setProgress(20);
    $this->setDivideProgressBy(5);

    $sourceTar = str_replace('//', '/', $this->getTargetPath() . '/' . $tarFile);

    $sz = new self();
    $sz
      ->format('tar')
      ->deleteSourceAfterExtract()
      ->setCustomFlags($this->getCustomFlags())
      ->source($sourceTar)
      ->target($this->getTargetPath());


    $sz->progress($this->getProgressCallback());


    // 'snoi' => store owner id in archive, extract owner id from archive (tar/Linux)
    // 'snon' => store owner name in archive (tar/Linux)
    // 'mtc'  => Stores Creation timestamps for files (for pax method).
    // 'mta'  => Stores last Access timestamps for files (for pax method).
    // 'mtm'  => Stores last Modification timestamps for files ).
    if ($this->shouldKeepFileInfoOnTar()) {
      $sz
        ->addFlag('snoi')
//        ->addFlag('snon') // @FIXME on linux causes an error "Segmentation fault"
        ->addFlag('mtc', 'on')
        ->addFlag('mta', 'on')
        ->addFlag('mtm', 'on');
    } else {
      $sz->addFlag('mtc', 'off')->addFlag('mta', 'off')->addFlag('mtm', 'off');
    }

    $output = $this->runCommand($extractCommand);
    $output .= $sz->extract();
    unset($sz);

    return $output;
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

  public function deleteSourceAfterExtract(bool $delete = true): SevenZip
  {
    $this->deleteSourceAfterExtract = $delete;
    return $this;
  }

  /**
   * Set the progress callback using a fluent interface.
   *
   * @param ?callable $callback The callback function to be called during the compression progress.
   * @return $this The current instance of the SevenZip class.
   */
  public function progress(?callable $callback): self
  {
    return $this->setProgressCallback($callback);
  }

  /**
   * Get whether to keep file info when using TAR before compression.
   *
   * @return bool Whether to keep file info when using TAR before compression.
   */
  public function shouldKeepFileInfoOnTar(): bool
  {
    return $this->keepFileInfoOnTar;
  }

  public function autoUntar(bool $auto = true): SevenZip
  {
    $this->autoUntar = $auto;
    return $this;
  }

  /**
   * Compress a file or directory.
   *
   * @return string The output of the 7-Zip command.
   * @throws \InvalidArgumentException If target path, or source path is not set.
   *
   */
  public function compress(): string
  {
    if (!$this->getTargetPath()) {
      throw new \InvalidArgumentException(
        "Archive file path (target) must be set"
      );
    }

    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException(
        "File or directory path (source) must be set"
      );
    }

    if ($this->getFormat() === "zip") {
      if (!$this->getFlag("mm")) {
        $this->mm("Deflate64");
      }

      if ($this->getPassword()) {
        $this->addFlag("mem", $this->getZipEncryptionMethod());
      }
    }

    if (
      $this->getPassword() &&
      $this->getEncryptNames() &&
      $this->getFormat() !== "zip"
    ) {
      $this->addFlag("mhe");
    }

    if ($this->getPassword()) {
      $this->addFlag("p", $this->getPassword(), glued: true);
    }

    if ($this->shouldForceTarBefore()) {
      $this->executeTarBefore();
    }

    $command = [
      $this->sevenZipPath,
      "a",
      ...$this->flagrize($this->getAlwaysFlags()),
      ...$this->flagrize($this->getFormatFlags()),
      ...$this->flagrize($this->getCustomFlags()),
      $this->getTargetPath(),
      $this->getSourcePath(),
    ];

    return $this->runCommand($command);
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
    return $this->addFlag("mm", $method);
  }

  /**
   * Get the ZIP encryption method.
   *
   * @return string The ZIP encryption method.
   */
  public function getZipEncryptionMethod(): string
  {
    return $this->zipEncryptionMethod;
  }

  /**
   * Set the ZIP encryption method.
   *
   * @param string $zipEncryptionMethod The ZIP encryption method to be used.
   * @return $this The current instance of the SevenZip class.
   */
  public function setZipEncryptionMethod(string $zipEncryptionMethod): SevenZip
  {
    $this->zipEncryptionMethod = $zipEncryptionMethod;
    return $this;
  }

  public function getEncryptNames(): ?bool
  {
    return $this->encryptNames;
  }

  /*
   * Sets level of file analysis.
   *
   * @param int $level
   * @return $this
   */

  /**
   * Set whether to encrypt file names or not.
   *
   * @param bool $encrypt Whether or not to encrypt file names.
   * @return $this The current instance of the SevenZip class.
   */
  public function setEncryptNames(?bool $encrypt): SevenZip
  {
    $this->encryptNames = $encrypt;
    return $this;
  }

  /**
   * Get whether to force TAR before compression.
   *
   * @return bool Whether to force TAR before compression.
   */
  public function shouldForceTarBefore(): bool
  {
    return $this->forceTarBefore;
  }

  /**
   * Tars the source file or directory before compressing.
   *
   * @return $this The current instance of the SevenZip class.
   */
  protected function executeTarBefore(): self
  {
    if ($this->wasAlreadyTarred()) {
      return $this;
    }

    if (!$this->getSourcePath()) {
      throw new \InvalidArgumentException(
        "File or directory path (source) must be set"
      );
    }

    $sourcePath = $this->getSourcePath();
    $targetPath = $this->getTargetPath();
    $tarPath    = sys_get_temp_dir() . '/' . uniqid('sevenzip_') . '/' . pathinfo($targetPath, PATHINFO_FILENAME);
    if (substr($tarPath, -4) !== '.tar') {
      $tarPath .= '.tar';
    }
    try {
      $sz = new self();
      $sz
        ->format("tar")
        ->target($tarPath)
        ->source($sourcePath);

      $partOfTotal = 5;
      $sz->setDivideProgressBy($partOfTotal);
      $this->setLastProgress(100 / $partOfTotal);
      $sz->progress($this->getProgressCallback());


      // 'snoi' => store owner id in archive, extract owner id from archive (tar/Linux)
      // 'snon' => store owner name in archive (tar/Linux)
      // 'mtc'  => Stores Creation timestamps for files (for pax method).
      // 'mta'  => Stores last Access timestamps for files (for pax method).
      // 'mtm'  => Stores last Modification timestamps for files ).
      if ($this->shouldKeepFileInfoOnTar()) {
        $sz
          ->addFlag("snoi")
//          ->addFlag("snon") // @FIXME on linux causes a error "Segmentation fault"
          ->addFlag("mtc", "on")
          ->addFlag("mta", "on")
          ->addFlag("mtm", "on");
      } else {
        $sz->addFlag("mtc", "off")->addFlag("mta", "off")->addFlag("mtm", "off");
      }

      $sz->compress();

      unset($sz);

      $this->setAlreadyTarred(true);

      $this->setSourcePath($tarPath)->deleteSourceAfterCompress();
    }
    catch (Exception $e) {
      @unlink($tarPath);
      throw $e;
      // @TODO use native tar?
    }

    return $this;
  }

  /**
   * Get whether the source is already TARred.
   *
   * @return bool Whether the source is already TARred.
   */
  public function wasAlreadyTarred(): bool
  {
    return $this->alreadyTarred;
  }

  /**
   * Set whether the source is already TARred.
   *
   * @param bool $alreadyTarred Whether the source is already TARred.
   * @return $this The current instance of the SevenZip class.
   */
  public function setAlreadyTarred(bool $alreadyTarred): SevenZip
  {
    $this->alreadyTarred = $alreadyTarred;
    return $this;
  }

  /**
   * Configure to delete the source file or directory after compression (alias for sdel)
   *
   * @return $this
   */
  public function deleteSourceAfterCompress(): self
  {
    return $this->sdel();
  }

  /**
   * Configure to delete the source file or directory after compression (same of deleteSourceAfterCompress())
   * @return $this
   */
  public function sdel(): self
  {
    return $this->addFlag("sdel");
  }

  /**
   * Get the default compression flags for the specified format.
   *
   * @return array The default compression flags for the specified format.
   */
  protected function getFormatFlags(): array
  {
    return $this->formatFlags ?? [];
  }

  public function setFormatFlags(): SevenZip
  {
    $this->formatFlags = match ($this->getFormat()) {
      'zip', 'tar.zip'                    => ['tzip'],
      '7z', 'lzma2', 'tar.7z'             => ['t7z', 'm0' => 'lzma2'],
      'lz4', 'tar.lz4'                    => ['t7z', 'm0' => 'lz4'],
      'lz5', 'tar.lz5'                    => ['t7z', 'm0' => 'lz5'],
      'bz2', 'bzip2', 'tar.bz2', 'tar.bz' => ['t7z', 'm0' => 'bzip2'],
      'zstd', 'zst',                      => ['t7z', 'm0' => 'zstd'],
      'brotli', 'br'                      => ['t7z', 'm0' => 'brotli'],
      'gzip', 'gz'                        => ['t7z', 'm0' => 'gzip'],
      'tar'                               => [
        'ttar',
        'mm'  => 'pax', // Modern POSIX
        'mtp' => '1', // Sets timestamp precision to 1 second (unix timestamp)
      ],
      // Down below if not supported will throw an exception
      'tar.zstd', 'tar.zst', 'tzst'       => ['tzstd'],
      'tgz', 'tar.gz', 'tgzip'            => ['tgzip'],
      'tbr', 'tar.br', 'tar.brotli'       => ['tbrotli'],
      default                             => ['t' . $this->getFormat()],
    };

    $needsTar = strpos($this->getFormat(), 't') === 0 && $this->getFormat() !== 'tar';

    return $needsTar ? $this->tarBefore() : $this;
  }

  /**
   * Set the compression level to faster.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function faster(): self
  {
    if ($this->getFormat() === "zstd" || $this->getFormat() === "zst") {
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
    return $this->addFlag("mx", $level);
  }

  /**
   * Set the compression level to slower.
   *
   * @return $this The current instance of the SevenZip class.
   */
  public function slower(): self
  {
    if ($this->getFormat() === "zstd" || $this->getFormat() === "zst") {
      return $this->mx(22);
    }

    return $this->mx(9);
  }

  /**
   * Configures maximum compression settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function ultra(): self
  {
    $this->mmt(true)->mx(9);

    return match ($this->getFormat()) {
      "zip"         => $this->mm("Deflate64")->mfb(257)->mpass(15)->mmem(28),
      "gzip"        => $this->mfb(258)->mpass(15),
      "bzip2"       => $this->mpass(7)->md("900000b"),
      "7z"          => $this->m0("lzma2")->mfb(64)->ms(true)->md("32m"),
      "zstd", "zst" => $this->mx(22),
      default       => $this,
    };
  }

  public function mmem(int|string $size = 24)
  {
    return $this->addFlag("mmem", $size);
  }

  /**
   * Set the number of passes for compression.
   *
   * @param int $number The number of passes for compression.
   * @return $this The current instance of the SevenZip class.
   */
  public function mpass(int $number = 7): self
  {
    return $this->addFlag("mpass", $number);
  }

  /**
   * Set the size of the Fast Bytes for the compression algorithm.
   *
   * @param int $bytes The size of the Fast Bytes. The default value (when set) is 64.
   * @return $this The current instance of the SevenZip class.
   */
  public function mfb(int $bytes = 64): self
  {
    return $this->addFlag("mfb", $bytes);
  }

  public function md(string $size = "32m"): self
  {
    return $this->addFlag("md", $size);
  }

  public function ms(bool|string|int $on = true): self
  {
    return $this->addFlag("ms", $on ? "on" : "off");
  }

  /**
   * Set the main/first compression method.
   * @param $method string The compression method to be used.
   * @return $this
   */
  public function m0(string $method): self
  {
    return $this->addFlag("m0", $method);
  }

  /**
   * Set the second compression method.
   *
   * @param $method string The compression method to be used.
   * @return $this
   */
  public function m1($method): self
  {
    return $this->addFlag("m1", $method);
  }

  /**
   * Set the third compression method
   *
   * @param $method string The compression method to be used.
   * @return $this
   */
  public function m2(string $method): self
  {
    return $this->addFlag("m2", $method);
  }

  public function solid(bool|string|int $on = true): self
  {
    return $this->ms($on);
  }

  /**
   * Configures no compression (copy only) settings based on the specified format.
   *
   * @return static The current instance for method chaining.
   */
  public function copy(): self
  {
    return $this->mmt(true)->mx(0)->m0("Copy")->mm("Copy")->myx(0);
  }

  /**
   * Sets file analysis level.
   *
   * @param int $level
   * @return $this
   */
  public function myx(int $level = 5): self
  {
    return $this->addFlag("myx", $level);
  }

  /**
   * Get the idle timeout value.
   *
   * @return int The idle timeout value in seconds.
   */
  public function getIdleTimeout(): int
  {
    return $this->idleTimeout;
  }

  /**
   * Set the idle timeout value.
   *
   * @param int $idleTimeout The idle timeout value in seconds.
   * @return $this The current instance of the SevenZip class.
   */
  public function setIdleTimeout(int $idleTimeout): SevenZip
  {
    $this->idleTimeout = $idleTimeout;
    return $this;
  }
}
