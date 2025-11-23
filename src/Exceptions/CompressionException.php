<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

/**
 * Exception thrown when compression fails.
 */
class CompressionException extends SevenZipException {
    public function __construct(
        string $message = 'Compression failed.',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
