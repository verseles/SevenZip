<?php

namespace Verseles\SevenZip\Contracts;

/**
 * Interface for extraction operations.
 */
interface ExtractorInterface {
    /**
     * Extract an archive.
     *
     * @return string The output of the extraction command.
     *
     * @throws \Verseles\SevenZip\Exceptions\ExtractionException If extraction fails.
     */
    public function extract() : string;

    /**
     * List the files inside an archive.
     *
     * @return array<int, array<string, mixed>> The list of files inside the archive.
     */
    public function fileList() : array;

    /**
     * Get information about an archive and its contents.
     *
     * @return array<string, mixed> An array containing archive information and file list.
     */
    public function fileInfo() : array;

    /**
     * Set whether to automatically extract inner tar archives.
     */
    public function autoUntar(bool $auto = TRUE) : static;
}
