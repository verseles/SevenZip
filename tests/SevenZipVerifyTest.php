<?php

namespace Verseles\SevenZip\Tests;

use PHPUnit\Framework\TestCase;
use Verseles\SevenZip\SevenZip;

class SevenZipVerifyTest extends TestCase
{
    private $sevenZip;
    private $testDir;

    protected function setUp(): void
    {
        $this->sevenZip = new SevenZip();
        $this->testDir = realpath(__DIR__ . '/../test_files');
    }

    public function testVerifyValidArchive(): void
    {
        $archive = $this->testDir . '/target/verify_valid.7z';
        $source = $this->testDir . '/source';

        if (file_exists($archive)) {
            unlink($archive);
        }

        // Create a valid archive
        $this->sevenZip
            ->format('7z')
            ->source($source)
            ->target($archive)
            ->compress();

        // Verify it
        $this->sevenZip->source($archive);

        // We expect verify() to be available
        // If it's not implemented yet, this will fail with "Call to undefined method"
        $output = $this->sevenZip->verify();

        $this->assertStringContainsString('Everything is Ok', $output);

        if (file_exists($archive)) {
            unlink($archive);
        }
    }

    public function testVerifyInvalidArchive(): void
    {
        $archive = $this->testDir . '/target/verify_invalid.7z';

        if (file_exists($archive)) {
            unlink($archive);
        }

        // Create a dummy file that is not a valid archive
        file_put_contents($archive, 'This is not a 7z archive');

        $this->expectException(\RuntimeException::class);

        try {
            $this->sevenZip->source($archive)->verify();
        } finally {
             if (file_exists($archive)) {
                unlink($archive);
            }
        }
    }
}
