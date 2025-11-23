<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

/**
 * Exception thrown when extraction fails.
 */
class ExtractionException extends SevenZipException {
    public function __construct(
        string $message = 'Extraction failed.',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
