<?php

namespace Verseles\SevenZip\Contracts;

/**
 * Interface for archive testing operations.
 */
interface TesterInterface {
    /**
     * Test the integrity of an archive.
     *
     * @return bool TRUE if the archive is valid, FALSE otherwise.
     */
    public function test() : bool;

    /**
     * Test the integrity of an archive and return detailed information.
     *
     * @return array<string, mixed> An array containing test results with 'valid' and 'details' keys.
     */
    public function testWithDetails() : array;
}
