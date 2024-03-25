# SevenZip 🔧

A PHP package to compress and decompress files using 7zip CLI.

![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/verseles/SevenZip/phpunit.yml?style=for-the-badge&label=PHPUnit)

## Installation

Install the package via Composer:

```bash
composer require verseles/sevenzip
```

## Usage

### Compression

To compress a file or directory:

```php
use Verseles\SevenZip\SevenZip;

$sevenZip = new SevenZip();

$format = '7z'; // Compression format (e.g., '7z', 'zip', 'tar')
$archivePath = '/path/to/archive.7z';
$sourcePath = '/path/to/source/file/or/directory';

$sevenZip->compress($format, $archivePath, $sourcePath);
```

You can also use the fluent interface:

```php
$sevenZip = new SevenZip();
$sevenZip->format('7z')
         ->source('/path/to/source/file/or/directory')
         ->target('/path/to/archive.7z')
         ->compress();
```

#### Compression Options

- **Format**: You can set the compression format using the `format()` method. Supported formats include `7z`, `zip`, `tar`, `lz4`, `lz5`, `bzip2`,
  and `zstd`.
- **Compression Level**: You can adjust the compression level using the `faster()`, `slower()`, `mx()`, and `ultra()` methods.
- **Custom Flags**: You can add custom compression flags using the `addFlag()` method.
- **Progress Callback**: You can set a progress callback using the `progress()` method to monitor the compression progress.

### Extraction

To extract an archive:

```php
use Verseles\SevenZip\SevenZip;

$sevenZip = new SevenZip();

$format = '7z'; // Archive format (e.g., '7z', 'zip', 'tar')
$archivePath = '/path/to/archive.7z';
$extractPath = '/path/to/extract/directory';

$sevenZip->extract($format, $archivePath, $extractPath);
```

You can also use the fluent interface:

```php
$sevenZip = new SevenZip();
$sevenZip->source('/path/to/archive.7z')
         ->target('/path/to/extract/directory')
         ->extract();
```

## Supported Formats

The package supports any format, but the following formats are aliased:

- 7z (default to lzma2)
- zip
- tar
- lz4
- lz5
- bzip2
- zstd

> [!WARNING]
> The format support depends on your system, architecture, etc.
> You can always use `format()` method to set your custom format.

## TODO / WIP

- [ ] Add custom support for gz, xz, etc. by using tar flags
- [ ] Use tar to keep original file permissions and other attributes

## Contributing

Contributions are welcome! If you'd like to contribute to this project, please follow these steps:

1. Fork the repository
2. Create a new branch for your feature or bug fix
3. Make your changes and commit them with descriptive commit messages
4. Push your changes to your forked repository
5. Submit a pull request to the main repository

Please ensure that your code follows the project's coding style and conventions. Also, include appropriate tests for your changes.

## Testing

To run the tests, execute the following command:

```bash
make
```

# Documentation / API

## `__construct(?string $sevenZipPath = null)`

Constructs a new SevenZip instance. If `$sevenZipPath` is not provided, it will attempt to automatically detect the 7-Zip executable. If the
executable is not found, an `ExecutableNotFoundException` will be thrown.

**Parameters**

- `$sevenZipPath` (optional): Path to the 7-Zip executable file.

**Throws**

- `ExecutableNotFoundException`: If the 7-Zip executable is not found.

## `getSevenZipPath(): ?string`

Gets the path to the 7-Zip executable file.

**Returns**: Path to the 7-Zip executable file.

## `setSevenZipPath(string $sevenZipPath): SevenZip`

Sets the path to the 7-Zip executable file.

**Parameters**

- `$sevenZipPath`: Path to the 7-Zip executable file.

**Returns**: The current instance of the SevenZip class.

## `source(string $path): static`

Sets the source path for the compression or extraction operation.

**Parameters**

- `$path`: The source path.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->source('/path/to/source/file/or/directory');
```

## `format(string $format): static`

Sets the archive format.

**Parameters**

- `$format`: The compression format to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->format('7z');
```

## `compress(?string $format = null, ?string $sourcePath = null, ?string $targetPath = null): bool`

Compresses a file or directory.

**Parameters**

- `$format` (optional): Archive format.
- `$sourcePath` (optional): Path to the file or directory to compress.
- `$targetPath` (optional): Path to the compressed archive.

**Returns**: `true` on success.

**Throws**

- `InvalidArgumentException`: If format, target path, or source path is not set.

**Example**

```php
$sevenZip->compress('7z', '/path/to/source', '/path/to/archive.7z');
```

## `getTargetPath(): string`

Gets the target path for compression/extraction.

**Returns**: The path to the target file or directory for compression or extraction.

## `setTargetPath(string $path): static`

Sets the target path for compression/extraction.

**Parameters**

- `$path`: The path to the target file or directory for compression or extraction.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setTargetPath('/path/to/archive.7z');
```

## `getSourcePath(): string`

Gets the source path for compression/extraction.

**Returns**: The path to the source file or directory for compression or extraction.

## `setSourcePath(string $path): static`

Sets the source path for compression/extraction.

**Parameters**

- `$path`: The path to the source file or directory for compression or extraction.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setSourcePath('/path/to/source/file/or/directory');
```

## `flagrize(array $flagsAndValues): array`

Formats flags and values into an array of strings suitable for passing to 7-Zip commands.

**Parameters**

- `$flagsAndValues`: An associative array of flags and their corresponding values. If the value is null, the flag will be added without an equal sign.

**Returns**: An array of formatted flag strings.

**Example**

```php
$formattedFlags = $sevenZip->flagrize(['m0' => 'lzma2', 'mx' => 9]);
// Output: ['-m0=lzma2', '-mx=9']
```

## `getFormat(): string`

Gets the archive format.

**Returns**: The compression format to be used.

## `setFormat(string $format): static`

Sets the archive format.

**Parameters**

- `$format`: The compression format to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setFormat('zip');
```

## `getCustomFlags(): array`

Gets the custom compression flags.

**Returns**: The custom compression flags that have been added.

## `setCustomFlags(array $customFlags): SevenZip`

Sets the custom compression flags.

**Parameters**

- `$customFlags`: The custom compression flags to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setCustomFlags(['mx' => 9, 'mfb' => 64]);
```

## `progress(callable $callback): static`

Sets the progress callback using a fluent interface.

**Parameters**

- `$callback`: The callback function to be called during the compression progress.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->progress(function ($progress) {
    echo "Progress: {$progress}%\n";
});
```

## `faster(): static`

Sets the compression level to faster.

**Returns**: The current instance of the SevenZip class.

## `mx(int $level): static`

Sets the compression level using the `-mx` flag.

**Parameters**

- `$level`: The compression level to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mx(9); // Maximum compression
```

## `addFlag(string $flag, $value = null): static`

Adds a compression flag.

**Parameters**

- `$flag`: The compression flag to be added.
- `$value` (optional): The value for the flag.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->addFlag('mfb', 64);
```

## `slower(): static`

Sets the compression level to slower.

**Returns**: The current instance of the SevenZip class.

## `ultra(): static`

Configures maximum compression settings based on the specified format.

**Returns**: The current instance of the SevenZip class.

## `mmt(int|bool|string $threads = 'on'): static`

Sets the number of CPU threads to use for compression.

**Parameters**

- `$threads`: The number of CPU threads to use, or 'on' or 'off'.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mmt('on'); // Use all available CPU threads
$sevenZip->mmt(4); // Use 4 CPU threads
```

## `mmem(int|string $size = 24): static`

Sets the memory limit for compression.

**Parameters**

- `$size`: The memory limit in megabytes or as a string (e.g., '32m').

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mmem(32); // Set memory limit to 32 MB
```

## `mpass(int $number = 7): static`

Sets the number of passes for compression.

**Parameters**

- `$number`: The number of passes for compression.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mpass(15); // Use 15 compression passes
```

## `reset(): SevenZip`

Resets the property values to their original state.

**Returns**: The current instance of the SevenZip class.

This method resets the `customFlags`, `progressCallback`, `lastProgress`, `format`, `targetPath`, and `sourcePath` properties to their default values.

## License

This package is open-sourced software licensed under the [MIT license](./LICENSE.md).
