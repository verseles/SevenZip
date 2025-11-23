<?php

namespace Verseles\SevenZip\Exceptions;

use Throwable;

/**
 * Exception thrown when the password is incorrect or missing for encrypted archives.
 */
class InvalidPasswordException extends SevenZipException {
    public function __construct(
        string $message = 'Invalid or missing password for encrypted archive.',
        int $code = 0,
        ?Throwable $previous = NULL,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
