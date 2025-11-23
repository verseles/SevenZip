# Migration Guide: v1.x / v2.0.x to v2.1

This document describes the breaking changes introduced in version 2.1 and provides guidance on how to migrate your code.

## Overview

Version 2.0 introduces significant improvements to the codebase:

- **New Exception Hierarchy**: Specific exception types for different error scenarios
- **Interface Contracts**: Well-defined interfaces for better testability and extensibility
- **Archive Testing**: New methods to verify archive integrity
- **Static Analysis**: Full PHPStan level 6 compliance with proper type hints
- **Code Quality**: Laravel Pint (PSR-12) code style enforcement

## Breaking Changes

### 1. Exception Handling

#### Before (v1.x)
```php
use Verseles\SevenZip\Exceptions\ExecutableNotFoundException;

try {
    $sevenZip->compress();
} catch (\RuntimeException $e) {
    // All errors were RuntimeException
}
```

#### After (v2.1)
```php
use Verseles\SevenZip\Exceptions\SevenZipException;
use Verseles\SevenZip\Exceptions\CompressionException;
use Verseles\SevenZip\Exceptions\ExtractionException;
use Verseles\SevenZip\Exceptions\InvalidFormatException;
use Verseles\SevenZip\Exceptions\InvalidPasswordException;
use Verseles\SevenZip\Exceptions\ArchiveNotFoundException;
use Verseles\SevenZip\Exceptions\ExecutableNotFoundException;

try {
    $sevenZip->compress();
} catch (CompressionException $e) {
    // Compression-specific error
} catch (SevenZipException $e) {
    // Any 7-Zip related error (base class)
}
```

#### New Exception Hierarchy

```
SevenZipException (base class)
├── CompressionException
├── ExtractionException
├── InvalidFormatException
├── InvalidPasswordException
├── ArchiveNotFoundException
└── ExecutableNotFoundException (now extends SevenZipException)
```

**Migration Action**: Update your `catch` blocks to use the new exception types, or catch `SevenZipException` to handle all 7-Zip related errors.

### 2. Return Type Changes: `self` → `static`

All fluent methods now return `static` instead of `self` to properly support inheritance.

#### Before (v1.x)
```php
// If you extended SevenZip, fluent methods returned SevenZip, not your class
class MySevenZip extends SevenZip {
    public function myMethod(): self {
        return $this->format('7z'); // Returned SevenZip, not MySevenZip
    }
}
```

#### After (v2.1)
```php
// Now works correctly with inheritance
class MySevenZip extends SevenZip {
    public function myMethod(): static {
        return $this->format('7z'); // Returns MySevenZip
    }
}
```

**Migration Action**: If you extended the `SevenZip` class and overrode methods, update your return types from `self` to `static`.

### 3. Method Signature Changes

#### `addFlag()` Parameter Type

The `$value` parameter is now explicitly nullable:

```php
// Before (v1.x) - implicit null
public function addFlag(string $flag, string $value = null, bool $glued = false): self

// After (v2.1) - explicit nullable
public function addFlag(string $flag, ?string $value = null, bool $glued = false): static
```

**Migration Action**: If you call `addFlag()` with non-string values (like integers), cast them to string:

```php
// Before
$sevenZip->addFlag('mx', 9);

// After
$sevenZip->addFlag('mx', '9');
// Or use the dedicated method:
$sevenZip->mx(9);
```

#### `m1()` Parameter Type

The `$method` parameter now has an explicit `string` type:

```php
// Before (v1.x)
public function m1($method): self

// After (v2.1)
public function m1(string $method): static
```

**Migration Action**: Ensure you pass a string to `m1()`.

### 4. New Interfaces

The `SevenZip` class now implements four interfaces:

```php
class SevenZip implements
    ArchiveInterface,
    CompressorInterface,
    ExtractorInterface,
    TesterInterface
```

#### Available Interfaces

**ArchiveInterface**
```php
interface ArchiveInterface {
    public function source(string $path): static;
    public function target(?string $path): static;
    public function format(string $format): static;
    public function setPassword(string $password): static;
    public function getSourcePath(): ?string;
    public function getTargetPath(): ?string;
    public function getFormat(): string;
}
```

**CompressorInterface**
```php
interface CompressorInterface {
    public function compress(): string;
    public function faster(): static;
    public function slower(): static;
    public function ultra(): static;
    public function mx(int $level): static;
}
```

**ExtractorInterface**
```php
interface ExtractorInterface {
    public function extract(): string;
    public function fileList(): array;
    public function fileInfo(): array;
    public function autoUntar(bool $auto = true): static;
}
```

**TesterInterface**
```php
interface TesterInterface {
    public function test(): bool;
    public function testWithDetails(): array;
}
```

**Migration Action**: You can now type-hint against these interfaces for better dependency injection:

```php
// Before
public function compressFiles(SevenZip $compressor) { ... }

// After - more flexible
public function compressFiles(CompressorInterface $compressor) { ... }
```

## New Features

### Archive Integrity Testing

New methods to verify archive integrity:

```php
$sevenZip = new SevenZip();
$sevenZip->source('/path/to/archive.7z');

// Simple validation
if ($sevenZip->test()) {
    echo "Archive is valid!";
}

// Detailed validation
$result = $sevenZip->testWithDetails();
// Returns:
// [
//     'valid' => true,
//     'output' => '...',  // Full 7z output
//     'files' => [...]    // List of tested files
// ]
```

### Improved Password Security

Passwords are now masked in error messages to prevent accidental exposure in logs:

```php
// If compression fails, the error message will show:
// "Command: 7z a -p******** archive.7z ..."
// Instead of the actual password
```

### Parsed Info Caching

The `getParsedInfo()` method now caches results to avoid repeated subprocess calls when checking supported formats multiple times.

## Development Tools

### New Composer Scripts

```bash
# Run Laravel Pint (code style fixer)
composer lint

# Check code style without fixing
composer lint:check

# Run PHPStan static analysis
composer analyse

# Run tests with coverage
composer test:coverage

# Run all CI checks
composer ci
```

### Configuration Files

New configuration files added:
- `pint.json` - Laravel Pint configuration (PSR-12)
- `phpstan.neon` - PHPStan level 6 configuration
- `phpunit.xml` - PHPUnit with coverage support

## PHP Version Requirements

- **Minimum**: PHP 8.2
- **Tested**: PHP 8.2, 8.3, 8.4

## Quick Migration Checklist

- [ ] Update exception handling to use new exception types
- [ ] If extending `SevenZip`, change `self` return types to `static`
- [ ] Cast integer values to strings when calling `addFlag()`
- [ ] Add string type to `m1()` calls if passing variables
- [ ] (Optional) Use interfaces for type hints
- [ ] (Optional) Use new `test()` and `testWithDetails()` methods
- [ ] Run `composer analyse` to verify type compatibility

## Getting Help

If you encounter issues during migration:

1. Check that all exception catches are updated
2. Run `composer analyse` to find type issues
3. Review the [IMPROVEMENTS.md](IMPROVEMENTS.md) document for context
4. Open an issue on GitHub with details

## Full Changelog

See the commit history for detailed changes:

```bash
git log --oneline v1.0.0..v2.1.0
```
