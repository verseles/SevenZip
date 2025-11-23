<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

/**
 * Exception thrown when an invalid or unsupported format is specified.
 */
class InvalidFormatException extends SevenZipException {
    public function __construct(
        string $format = '',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        $message = $format
            ? "Invalid or unsupported archive format: {$format}"
            : 'Invalid or unsupported archive format.';

        parent::__construct($message, $code, $previous);
    }
}
