<?php

namespace Verseles\SevenZip\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all SevenZip related errors.
 */
class SevenZipException extends Exception {
    public function __construct(
        string $message = 'An error occurred during 7-Zip operation.',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
