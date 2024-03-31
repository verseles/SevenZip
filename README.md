# SevenZip 🔧

A PHP package to compress and decompress files using 7zip CLI.

![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/verseles/SevenZip/phpunit.yml?style=for-the-badge&label=PHPUnit)

## Installation

Install the package via Composer:

```bash
composer require verseles/sevenzip
```

<center>️Star the project to help us reach move devs!️</center>

## Usage

### Compression

To compress a file or directory:

```php
$sevenZip = new SevenZip();
$sevenZip->format('7z')
         ->source('/path/to/source/file/or/directory')
         ->target('/path/to/archive.7z')
         ->compress();
```

#### Compression Options

- **Format**: You can set the compression format using the `format()` method. Well known formats include `7z`, `zip`, `tar`, `lz4`, `lz5`, `bzip2`,
  and `zstd`. Your OS needs to support the selected format.
- **Compression Level**: You can adjust the compression level using the `faster()`, `slower()`, `mx()`, and `ultra()` methods.
- **Custom Flags**: You can add custom compression flags using the `addFlag()` method.
- **Progress Callback**: You can set a progress callback using the `progress()` method to monitor the compression progress.

> [!WARNING]
> The format support depends on your system, architecture, etc.
> You can always use `format()` method to set your custom format.

### Extraction

To extract an archive:

```php
$sevenZip = new SevenZip();
$sevenZip->source('/path/to/archive.7z')
         ->target('/path/to/extract/directory')
         ->extract();
```

### Encryption

You can encrypt the compressed archive using a password:

```php
$sevenZip = new SevenZip();
$sevenZip->source('/path/to/source/file/or/directory')
         ->target('/path/to/encrypted_archive.7z')
         ->encrypt('your_password')
         ->compress();
```

By default, the file names are also encrypted (not possible in zip format). If you want to disable file name encryption, you can use
the `notEncryptNames()` method:

```php
$sevenZip->notEncryptNames();
```

For ZIP archives, you can specify the encryption method using the `setZipEncryptionMethod()` method. Available options are 'ZipCrypto' (not secure), '
AES128', 'AES192', or 'AES256'. The default is 'AES256'.

```php
$sevenZip->setZipEncryptionMethod('AES256');
```

### Decryption

To decrypt an encrypted archive during extraction:

```php
$sevenZip = new SevenZip();
$sevenZip->source('/path/to/encrypted_archive.7z')
         ->target('/path/to/extract/directory')
         ->decrypt('your_password')
         ->extract();
```

## Including and Excluding Files

SevenZip allows you to include or exclude specific files when compressing or extracting archives.

### Including Files

To include specific files in the archive, use the `include` method:

```php
$sevenZip->include('*.avg')->compress();
```

### Excluding Files

To exclude specific files from the archive, use the `exclude` method:

```php
$sevenZip->exclude(['*.md', '*.pdf'])->compress();
```

Note that you can use both `include` and `exclude` methods together to fine-tune the files included in the archive.

> You can pass a single file pattern, an array of file patterns or the path to a txt file with a list of patterns inside to the `exclude`
> and `include` methods.

### Checking format support

You can check if a specific format or multiple formats are supported by the current 7-Zip installation using the checkSupport method:

```php
$sevenZip = new SevenZip();

// Check if a single format is supported
if ($sevenZip->checkSupport('zip')) {
    echo "ZIP format is supported.";
} else {
    echo "ZIP format is not supported.";
}

// Check if multiple formats are supported
if ($sevenZip->checkSupport(['zip', 'tar', '7z'])) {
    echo "ZIP, TAR, and 7Z formats are supported.";
} else {
    echo "One or more formats are not supported.";
}
```

## TODO / WIP

- [x] Full support for add flags (7z switches)
- [ ] Add custom support for gz, xz, etc. by using tar flags
- [ ] Use tar to keep original file permissions and other attributes
- [x] Filter files by patterns
- [x] Encrypt and decrypt
- [ ] Test files using 7z test command
- [x] Detect supported formats by the OS
- [x] Add built-in binaries for mac and linux
- [x] ~~Use docker for PHPUnit tests~~ not needed with built-in binaries

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
composer test
```

# Documentation / API

Here is the Documentation / API section of the README, updated with missing public methods and ordered alphabetically:

# Documentation / API

### `addFlag(string $flag, $value = null): static`

Adds a compression flag.

**Parameters**

- `$flag`: The compression flag to be added.
- `$value` (optional): The value for the flag.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->addFlag('mfb', 64);
```

### `checkSupport(string|array $extensions): bool`

Checks if the given extension(s) are supported by the current 7-Zip installation.

**Parameters**

- `$extensions`: The extension or an array of extensions to check.

**Returns**: Returns true if all the given extensions are supported, false otherwise.

**Example**

```php
$sevenZip = new SevenZip();

// Check if a single format is supported
$isZipSupported = $sevenZip->checkSupport('zip');

// Check if multiple formats are supported
$areFormatsSupported = $sevenZip->checkSupport(['zip', 'tar', '7z']);
```

### `compress(): string`

Compresses a file or directory.

**Returns**: the command output on success.

**Throws**

- `InvalidArgumentException`: If target path, or source path is not set.

**Example**

```php
$sevenZip->compress();
```

### `copy(): static`

Configures no compression (copy only) settings based on the specified format.

**Returns**: The current instance for method chaining.

### `decrypt(string $password): self`

Decrypts the data using the provided password.

**Parameters**

- `$password`: The password to decrypt the data.

**Returns**: The current instance of this class.

### `encrypt(string $password): self`

Encrypts the data using the provided password.

**Parameters**

- `$password`: The password to encrypt the data.

**Returns**: The current instance of this class.

### `extract(): string`

Extracts an archive.

**Returns**: the command output on success.

**Throws**

- `InvalidArgumentException`: If format, archive path, or extract path is not set.

**Example**

```php
$sevenZip->extract();
```

### `faster(): static`

Sets the compression level to faster.

**Returns**: The current instance of the SevenZip class.

### `flagrize(array $flagsAndValues): array`

Formats flags and values into an array of strings suitable for passing to 7-Zip commands.

**Parameters**

- `$flagsAndValues`: An associative array of flags and their corresponding values. If the value is null, the flag will be added without an equal sign.

**Returns**: An array of formatted flag strings.

**Example**

```php
$formattedFlags = $sevenZip->flagrize(['m0' => 'lzma2', 'mx' => 9]);
// Output: ['-m0=lzma2', '-mx=9']
```

### `format(string $format): static`

Sets the archive format.

**Parameters**

- `$format`: The compression format to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->format('7z');
```

### `getCustomFlags(): array`

Gets the custom compression flags.

**Returns**: The custom compression flags that have been added.

### `getEncryptNames(): ?bool`

Gets whether or not file names are encrypted.

**Returns**: Whether or not file names are encrypted, or null if not set.

### `getFormat(): string`

Gets the archive format.

**Returns**: The compression format to be used.

### `getInfo()`

Returns information about 7-Zip, formats, codecs, and hashers.

**Returns**: The output from the 7-Zip command.

### `getLastProgress(): int`

Gets the last reported progress.

**Returns**: The last reported progress percentage.

### `getPassword(): ?string`

Gets the password used for encryption or decryption.

**Returns**: The password used for encryption or decryption, or null if not set.

### `getSevenZipPath(): ?string`

Gets the path to the 7-Zip executable file.

**Returns**: Path to the 7-Zip executable file.

### `getSourcePath(): string`

Gets the source path for compression/extraction.

**Returns**: The path to the source file or directory for compression or extraction.

### `getSupportedFormatExtensions(?array $formats = null): array`

Gets all supported format extensions from the given array.

**Parameters**

- `$formats` (optional): The array of format data. If not provided, the built-in information from 7-Zip will be used.
  Returns: An array of supported format extensions.

**Example**

```php
$sevenZip = new SevenZip();
$supportedFormats = $sevenZip->getSupportedFormatExtensions();

if (in_array('zip', $supportedFormats)) {
echo "ZIP format is supported.";
} else {
echo "ZIP format is not supported.";
}
```

### `getTargetPath(): string`

Gets the target path for compression/extraction.

**Returns**: The path to the target file or directory for compression or extraction.

### `getZipEncryptionMethod(): string`

Gets the encryption method used for ZIP archives.

**Returns**: The encryption method used for ZIP archives.

### `exclude(string|array $patterns): self`

Excludes files from the archive based on the provided patterns.

**Parameters**

- `$patterns`: A single file pattern, an array of file patterns, or the path to a txt file with a list of patterns.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->exclude(['*.md', '*.pdf']);
```

### `include(string|array $patterns): self`

Includes only the specified files in the archive based on the provided patterns.

**Parameters**

- `$patterns`: A single file pattern, an array of file patterns, or the path to a txt file with a list of patterns.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->include('*.avg');
```

### `info(): void`

Prints the information about 7-Zip, formats, codecs, and hashers.

### `md(string $size = '32m'): self`

Sets the dictionary size for the compression algorithm.

**Parameters**

- `$size`: The dictionary size.

**Returns**: The current instance of the SevenZip class.

### `mfb(int $bytes = 64): self`

Sets the size of the Fast Bytes for the compression algorithm.

**Parameters**

- `$bytes`: The size of the Fast Bytes. The default value (when set) is 64.

**Returns**: The current instance of the SevenZip class.

### `mm(string $method): self`

Sets the compression method for ZIP format.

**Parameters**

- `$method`: The compression method to be used. Can be 'Copy', 'Deflate', 'Deflate64', 'BZip2', 'LZMA', 'PPMd'.

**Returns**: The current instance of the SevenZip class.

### `mmem(int|string $size = 24): static`

Sets the memory limit for compression.

**Parameters**

- `$size`: The memory limit in megabytes or as a string (e.g., '32m').

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mmem(32); // Set memory limit to 32 MB
```

### `mmt(int|bool|string $threads = 'on'): self`

Sets the number of CPU threads to use for compression.

**Parameters**

- `$threads`: The number of CPU threads to use, or 'on' or 'off'.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mmt('on'); // Use all available CPU threads
$sevenZip->mmt(4); // Use 4 CPU threads
```

### `m0($method): self`

Sets the compression method.

**Parameters**

- `$method`: The compression method to be used.

**Returns**: The current instance of the SevenZip class.

### `mpass(int $number = 7): self`

Sets the number of passes for compression.

**Parameters**

- `$number`: The number of passes for compression.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mpass(15); // Use 15 compression passes
```

### `ms(bool|string|int $on = true): self`

Enables or disables solid compression mode.

**Parameters**

- `$on`: Whether to enable or disable solid compression mode. Can be a boolean, 'on', or 'off'.

**Returns**: The current instance of the SevenZip class.

### `mx(int $level): static`

Sets the compression level using the `-mx` flag.

**Parameters**

- `$level`: The compression level to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->mx(9); // Maximum compression
```

### `myx(int $level = 5): self`

Sets the file analysis level.

**Parameters**

- `$level`: The file analysis level.

**Returns**: The current instance of the SevenZip class.

### `notEncryptNames(): self`

Disables encryption of file names.

**Returns**: The current instance of the SevenZip class.

### `progress(callable $callback): self`

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

### `removeFlag(string $flag): self`

Removes a compression flag.

**Parameters**

- `$flag`: The compression flag to be removed.

**Returns**: The current instance of the SevenZip class.

### `reset(): SevenZip`

Resets the property values to their original state.

**Returns**: The current instance of the SevenZip class.

### `setCustomFlags(array $customFlags): SevenZip`

Sets the custom compression flags.

**Parameters**

- `$customFlags`: The custom compression flags to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setCustomFlags(['mx' => 9, 'mfb' => 64]);
```

### `setEncryptNames(bool $encryptNames): self`

Sets whether or not to encrypt file names.

**Parameters**

- `$encryptNames`: Whether or not to encrypt file names.

**Returns**: The current instance of the SevenZip class.

### `setFormat(string $format): self`

Sets the archive format.

**Parameters**

- `$format`: The compression format to be used.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setFormat('zip');
```

### `setPassword(?string $password): self`

Sets the password for encryption or decryption.

**Parameters**

- `$password`: The password to be used for encryption or decryption.

**Returns**: The current instance of the SevenZip class.

### `setProgressCallback(callable $callback): self`

Sets the progress callback.

**Parameters**

- `$callback`: The callback function to be called during the compression progress.

**Returns**: The current instance of the SevenZip class.

### `setSevenZipPath(string $sevenZipPath): SevenZip`

Sets the path to the 7-Zip executable file.

**Parameters**

- `$sevenZipPath`: Path to the 7-Zip executable file.

**Returns**: The current instance of the SevenZip class.

### `setSourcePath(string $path): static`

Sets the source path for compression/extraction.

**Parameters**

- `$path`: The path to the source file or directory for compression or extraction.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setSourcePath('/path/to/source/file/or/directory');
```

### `setTargetPath(string $path): static`

Sets the target path for compression/extraction.

**Parameters**

- `$path`: The path to the target file or directory for compression or extraction.

**Returns**: The current instance of the SevenZip class.

**Example**

```php
$sevenZip->setTargetPath('/path/to/archive.7z');
```

### `setZipEncryptionMethod(string $method): self`

Sets the encryption method for ZIP archives.

**Parameters**

- `$method`: The encryption method to be used. Can be 'ZipCrypto' (not secure), 'AES128', 'AES192', or 'AES256'.

**Returns**: The current instance of the SevenZip class.

### `slower(): static`

Sets the compression level to slower.

**Returns**: The current instance of the SevenZip class.

### `source(string $path): self`

Sets the source path for the compression or extraction operation.

**Parameters**

- `$path`: The source path.

**Returns**: The current instance of the SevenZip class.

### `target(?string $path): self`

Sets the target path for compression/extraction using a fluent interface.

**Parameters**

- `$path`: The path to the target file or directory for compression or extraction.

**Returns**: The current instance of the SevenZip class.

### `ultra(): self`

Configures maximum compression settings based on the specified format.

**Returns**: The current instance for method chaining.

## License

This package is open-sourced software licensed under the [MIT license](./LICENSE.md).

> About 7zip binaries: Most of the source code is under the GNU LGPL license. The unRAR code is under a mixed license with GNU LGPL + unRAR
> restrictions. [Check the license for details](https://sourceforge.net/projects/sevenzip/).
