<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

/**
 * Exception thrown when the specified archive file is not found.
 */
class ArchiveNotFoundException extends SevenZipException {
    public function __construct(
        string $path = '',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        $message = $path
            ? "Archive file not found: {$path}"
            : 'Archive file not found.';

        parent::__construct($message, $code, $previous);
    }
}
