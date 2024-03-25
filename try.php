<?php
// run this: php -f try.php

require_once __DIR__ . '/vendor/autoload.php';

use Verseles\SevenZip\SevenZip;

$archivePath    = '/Users/helio/zip-tiny.7z';
$extractPath    = '/Users/helio/tmp';
$fileToCompress = '/Users/helio/zip-tiny';

@unlink($archivePath);
@unlink($extractPath . '/' . $fileToCompress);

echo "Creating instance of SevenZip... ";
$sevenZip = new SevenZip();
echo "✅\n";

echo 'Compressing archive... ';
$sevenZip
  ->setProgressCallback(function ($progress) {
    echo "\n" . $progress . "%\n";
  })
  ->format('7z')
  ->source($fileToCompress)
  ->target($archivePath)
  ->compress();
echo "✅\n";

echo 'Extracting archive... ';
$sevenZip
  ->setProgressCallback(function ($progress) {
    echo "\n" . $progress . "%\n";
  })
  ->source($archivePath)
  ->target($extractPath)
  ->extract();
echo "✅\n";

echo "Deleting archive... ";
unlink($archivePath);
echo "✅\n";

echo "Clearing directory... ";
clearDirectory($extractPath);
echo "✅\n";

/**
 * Clears a directory by removing all files and subdirectories within it,
 * but leaves the directory itself intact.
 *
 * @param string $dir The path to the directory to be cleared.
 * @return bool Returns true on success, false on failure.
 */
function clearDirectory($dir)
{
  if (!is_dir($dir)) {
    return false;
  }

  $files = array_diff(scandir($dir), ['.', '..']);

  foreach ($files as $file) {
    $filePath = $dir . '/' . $file;

    if (is_dir($filePath)) {
      clearDirectory($filePath);
      rmdir($filePath);
    } else {
      unlink($filePath);
    }
  }

  return true;
}
