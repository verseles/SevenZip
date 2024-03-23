<?php
// run this: php -f try.php

require_once __DIR__ . '/vendor/autoload.php';

use Verseles\SevenZip\SevenZip;

$archivePath    = './test_files/target/archive.7z';
$extractPath    = './test_files/extract';
$fileToCompress = 'try.php';

@unlink($archivePath);
@unlink($extractPath . '/' . $fileToCompress);

echo "Creating instance of SevenZip... ";
$sevenZip = new SevenZip();
echo "✅\n";

echo 'Compressing archive... ';
$sevenZip->compress('7z', $archivePath, $fileToCompress);
echo "✅\n";

echo 'Extracting archive... ';
$sevenZip->extract('7z', $archivePath, $extractPath);
echo "✅\n";

echo "Deleting archive... ";
unlink($archivePath);
echo "✅\n";
