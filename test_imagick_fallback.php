<?php
/**
 * Test script for ImageMagick wrapper fallback
 * This script checks if the ImageMagick wrapper works correctly
 * with both PHP Imagick extension and external ImageMagick
 */

// Simulate PHPWG_ROOT_PATH for testing
if (!defined('PHPWG_ROOT_PATH')) {
  define('PHPWG_ROOT_PATH', realpath(dirname(__FILE__) . '/../../') . '/');
}

// Load the wrapper
require_once(dirname(__FILE__) . '/lib/imagick_wrapper.php');

echo "=== ImageMagick Wrapper Fallback Test ===\n\n";

// Test 1: Check PHP Imagick extension
echo "Test 1: PHP Imagick Extension Status\n";
echo "--------------------------------------\n";
if (extension_loaded('imagick')) {
  echo "✅ PHP Imagick extension is LOADED\n";
  echo "   Version: " . phpversion('imagick') . "\n";
} else {
  echo "⚠️  PHP Imagick extension is NOT loaded\n";
}
echo "\n";

// Test 2: Check external ImageMagick availability
echo "Test 2: External ImageMagick CLI Tools\n";
echo "--------------------------------------\n";

$commands = array('convert', 'identify', 'magick');
$found_any = false;

foreach ($commands as $cmd) {
  $output = array();
  $return_var = 0;

  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $test_cmd = 'where ' . escapeshellarg($cmd);
  } else {
    $test_cmd = 'which ' . escapeshellarg($cmd);
  }

  @exec($test_cmd, $output, $return_var);

  if ($return_var === 0 && !empty($output[0])) {
    echo "✅ Found: $cmd at " . $output[0] . "\n";
    $found_any = true;
  } else {
    echo "❌ Not found: $cmd\n";
  }
}

if ($found_any) {
  echo "\n✅ External ImageMagick tools are available for fallback\n";
} else {
  echo "\n❌ No external ImageMagick tools found\n";
}
echo "\n";

// Test 3: Test wrapper with a sample image (if available)
echo "Test 3: Wrapper Functionality Test\n";
echo "----------------------------------\n";

// Create a simple test image
$test_image = sys_get_temp_dir() . '/test_imagick_wrapper_' . uniqid() . '.jpg';

// Try to create a minimal JPEG
$test_data = base64_decode(
  '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k='
);

if (file_put_contents($test_image, $test_data)) {
  echo "✅ Test image created: " . basename($test_image) . "\n";

  // Test the wrapper
  try {
    $wrapper = ImagickWrapper::load($test_image);

    if ($wrapper->hasError()) {
      echo "⚠️  Wrapper error: " . $wrapper->getError() . "\n";
    } else {
      echo "✅ Wrapper loaded successfully\n";
      echo "   Mode: " . $wrapper->getMode() . "\n";
      echo "   Using external: " . ($wrapper->isUsingExternal() ? 'Yes' : 'No') . "\n";

      // Try to get dimensions
      $width = $wrapper->getImageWidth();
      $height = $wrapper->getImageHeight();
      echo "   Dimensions: " . $width . "x" . $height . "\n";

      // Try to get XMP profile
      $xmp = $wrapper->getImageProfile('xmp');
      if ($xmp === false) {
        echo "   XMP: Not found (normal for test image)\n";
      } else {
        echo "   XMP: Found (" . strlen($xmp) . " bytes)\n";
      }

      $wrapper->destroy();
      echo "✅ Wrapper test PASSED\n";
    }
  } catch (Exception $e) {
    echo "❌ Wrapper test FAILED: " . $e->getMessage() . "\n";
  }

  @unlink($test_image);
} else {
  echo "⚠️  Could not create test image\n";
}
echo "\n";

// Test 4: Summary
echo "Test 4: Availability Summary\n";
echo "-----------------------------\n";

if (extension_loaded('imagick')) {
  echo "✅ PRIMARY:  PHP Imagick extension available\n";
} else if ($found_any) {
  echo "✅ FALLBACK: External ImageMagick CLI available\n";
  echo "   The plugin will use external ImageMagick tools\n";
} else {
  echo "❌ NO SOLUTION: Neither PHP Imagick nor external ImageMagick found\n";
  echo "   The plugin will NOT work\n";
}

echo "\n=== Test Complete ===\n";
?>
