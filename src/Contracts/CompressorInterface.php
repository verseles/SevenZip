<?php

namespace Verseles\SevenZip\Contracts;

/**
 * Interface for compression operations.
 */
interface CompressorInterface {
    /**
     * Compress a file or directory.
     *
     * @return string The output of the compression command.
     *
     * @throws \Verseles\SevenZip\Exceptions\CompressionException If compression fails.
     */
    public function compress() : string;

    /**
     * Set compression level to faster.
     */
    public function faster() : static;

    /**
     * Set compression level to slower (better compression).
     */
    public function slower() : static;

    /**
     * Configure maximum compression settings.
     */
    public function ultra() : static;

    /**
     * Set compression level using -mx flag.
     */
    public function mx(int $level) : static;
}
