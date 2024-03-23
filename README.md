# SevenZip 📦

A PHP package to compress and decompress files using 7zip CLI.

![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/verseles/SevenZip/phpunit.yml?style=for-the-badge&label=PHPUnit)

## Installation

Install the package via Composer:

```bash
composer require verseles/sevenzip
```

## Usage

To compress a file or directory:

```php
use Verseles\SevenZip\SevenZip;

$sevenZip = new SevenZip();

$format = '7z'; // Compression format (e.g., '7z', 'zip', 'tar')
$archivePath = '/path/to/archive.7z';
$sourcePath = '/path/to/source/file/or/directory';

$sevenZip->compress($format, $archivePath, $sourcePath);
```

To extract an archive:

```php
use Verseles\SevenZip\SevenZip;

$sevenZip = new SevenZip();

$format = '7z'; // Archive format (e.g., '7z', 'zip', 'tar')
$archivePath = '/path/to/archive.7z';
$extractPath = '/path/to/extract/directory';

$sevenZip->extract($format, $archivePath, $extractPath);
```

## Supported Formats

The package supports any format, but are aliased to the following:

- 7z (default to lzma2)
- zip
- tar
- lz4
- lz5
- bzip2
- zstd

> [!WARNING]
> The format support are bound to your system, arch, etc.
> If the wanted format are not in this list, please use addCustomCompressFlag() method.

## TODO / WIP

- [ ] Add custom support for gz, xz, etc. by using tar flags
- [ ] Use tar to keep original file permissions and other attributes
- [ ] Add extract custom flags

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

## License

This package is open-sourced software licensed under the [MIT license](./LICENSE.md).
