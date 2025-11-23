<?php

namespace Verseles\SevenZip\Contracts;

/**
 * Interface for archive operations.
 */
interface ArchiveInterface {
    /**
     * Set the source path for compression/extraction.
     */
    public function source(string $path) : static;

    /**
     * Set the target path for compression/extraction.
     */
    public function target(?string $path) : static;

    /**
     * Set the archive format.
     */
    public function format(string $format) : static;

    /**
     * Set the password for encryption/decryption.
     */
    public function setPassword(string $password) : static;

    /**
     * Get the source path.
     */
    public function getSourcePath() : ?string;

    /**
     * Get the target path.
     */
    public function getTargetPath() : ?string;

    /**
     * Get the current format.
     */
    public function getFormat() : string;
}
