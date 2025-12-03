# ImageMagick Wrapper - Usage Examples

## Basic Usage

### Loading an Image

```php
<?php
require_once('lib/imagick_wrapper.php');

// Load an image with automatic fallback
$wrapper = ImagickWrapper::load('/path/to/image.jpg');

// Check if there was an error
if ($wrapper->hasError()) {
  echo "Error: " . $wrapper->getError();
  exit;
}

// Use it like normal Imagick
$width = $wrapper->getImageWidth();
$height = $wrapper->getImageHeight();
echo "Image size: {$width}x{$height}";

// Don't forget cleanup
$wrapper->destroy();
?>
```

## Reading Metadata

### Get XMP Profile

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $xmp_data = $wrapper->getImageProfile('xmp');

  if ($xmp_data !== false) {
    echo "XMP found: " . strlen($xmp_data) . " bytes";
    // Process XMP data...
  } else {
    echo "No XMP data in image";
  }
}

$wrapper->destroy();
?>
```

### Get IPTC Profile

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $iptc_data = $wrapper->getImageProfile('iptc');

  if ($iptc_data !== false) {
    echo "IPTC found: " . strlen($iptc_data) . " bytes";
    // Process IPTC data...
  }
}

$wrapper->destroy();
?>
```

## Writing Metadata

### Set XMP Profile

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  // Create XMP data (simplified example)
  $xmp = '<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:xmp="http://ns.adobe.com/xap/1.0/">
    <xmp:Rating>5</xmp:Rating>
  </rdf:Description>
</rdf:RDF>';

  // Write XMP
  if ($wrapper->setImageProfile('xmp', $xmp)) {
    $wrapper->writeImage();
    echo "XMP written successfully";
  } else {
    echo "Failed to write XMP";
  }
}

$wrapper->destroy();
?>
```

### Set IPTC Profile

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $iptc_data = /* ... binary IPTC data ... */;

  if ($wrapper->setImageProfile('iptc', $iptc_data)) {
    $wrapper->writeImage();
    echo "IPTC written successfully";
  }
}

$wrapper->destroy();
?>
```

## Getting Image Information

### Image Dimensions

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $w = $wrapper->getImageWidth();
  $h = $wrapper->getImageHeight();
  $aspect_ratio = $w / $h;

  echo "Dimensions: {$w}x{$h} (aspect: {$aspect_ratio})";
}

$wrapper->destroy();
?>
```

### Image Properties

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $props = $wrapper->getImageProperties();

  foreach ($props as $key => $value) {
    echo "{$key}: {$value}\n";
  }
}

$wrapper->destroy();
?>
```

## Error Handling

### Complete Example with Error Handling

```php
<?php
$image_path = '/path/to/image.jpg';

try {
  $wrapper = ImagickWrapper::load($image_path);

  // Check for initialization errors
  if ($wrapper->hasError()) {
    error_log('ImageMagick initialization failed: ' . $wrapper->getError());
    return false;
  }

  // Get and process metadata
  $xmp = $wrapper->getImageProfile('xmp');
  if ($xmp === false) {
    error_log('No XMP profile found');
  } else {
    // Process XMP
    parseXMP($xmp);
  }

  // Get dimensions
  $width = $wrapper->getImageWidth();
  $height = $wrapper->getImageHeight();

  if ($width <= 0 || $height <= 0) {
    error_log('Invalid image dimensions');
    return false;
  }

  // Success
  return true;

} catch (Exception $e) {
  error_log('Exception: ' . $e->getMessage());
  return false;

} finally {
  // Always cleanup
  if (isset($wrapper)) {
    $wrapper->destroy();
  }
}
?>
```

## Checking Available Methods

### Get Current Mode

```php
<?php
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $mode = $wrapper->getMode();

  if ($mode === 'error') {
    echo "Not available";
  } elseif ($mode === 'php-imagick') {
    echo "Using PHP Imagick extension";
  } elseif ($mode === 'external') {
    echo "Using external ImageMagick CLI";
  }

  // Or use the convenience method
  if ($wrapper->isUsingExternal()) {
    error_log('Note: Using external ImageMagick (slower than native)');
  }
}

$wrapper->destroy();
?>
```

## Real-World Example: Face Tag Plugin

### Reading Face Data from XMP

```php
<?php
require_once('lib/imagick_wrapper.php');
require_once('lib/metadata_reader.php');

$image_path = $row['path'];
$reader = new FaceTagMetadataReader();

// The reader uses the wrapper internally
$metadata = $reader->readAll($image_path);

if (isset($metadata['error'])) {
  error_log('Cannot read metadata: ' . $metadata['error']);
} else {
  // Process faces
  $faces = $metadata['xmp']['faces'] ?? array();

  foreach ($faces as $face) {
    echo "Face: {$face['name']} at ({$face['x']}, {$face['y']})";
  }
}
?>
```

### Writing Face Data to XMP

```php
<?php
require_once('lib/imagick_wrapper.php');
require_once('lib/metadata_writer.php');

$image_path = $row['path'];
$faces = array(
  array('name' => 'John', 'x' => 0.5, 'y' => 0.5, 'w' => 0.3, 'h' => 0.4),
  array('name' => 'Jane', 'x' => 0.3, 'y' => 0.4, 'w' => 0.25, 'h' => 0.35),
);

$writer = new FaceTagMetadataWriterSimple();

// The writer uses the wrapper internally
$result = $writer->writeMetadata($image_path, $faces);

if ($result['success']) {
  echo "Faces written successfully";
} else {
  error_log('Failed to write faces: ' . $result['error']);
}
?>
```

## Debugging

### Enable Verbose Logging

```php
<?php
// Check which implementation is being used
$wrapper = ImagickWrapper::load('image.jpg');

if (!$wrapper->hasError()) {
  $mode = $wrapper->getMode();
  $using_external = $wrapper->isUsingExternal();

  // Log for debugging
  error_log("Wrapper mode: {$mode}");
  error_log("Using external: " . ($using_external ? 'yes' : 'no'));
}

$wrapper->destroy();
?>
```

### Check Available Tools

```php
<?php
// Test script from test_imagick_fallback.php
require_once('test_imagick_fallback.php');
// This will display:
// - PHP Imagick status
// - External ImageMagick availability
// - Wrapper test with sample image
?>
```

## Performance Considerations

### PHP Imagick (Native Extension)
- ✅ Fastest: ~10-50ms per operation
- ✅ Better for batch processing
- ✅ No external process overhead

### External ImageMagick (CLI)
- ⚠️ Slower: ~50-200ms per operation
- ⚠️ Process spawning overhead
- ✅ Still acceptable for single image operations
- ✅ Better than no support

### Best Practices
1. Minimize number of wrapper instantiations
2. Reuse wrapper instance if processing same image multiple times
3. Always call `destroy()` for cleanup
4. Batch operations: prefer PHP Imagick if available

## Troubleshooting Examples

### Check If External ImageMagick Works

```php
<?php
$wrapper = ImagickWrapper::load('test.jpg');

if ($wrapper->hasError()) {
  echo "Error: " . $wrapper->getError();
  echo "\nTry installing ImageMagick:";
  echo "\n  Debian/Ubuntu: apt-get install imagemagick";
  echo "\n  CentOS/RedHat: yum install ImageMagick";
  echo "\n  macOS: brew install imagemagick";
  exit;
}

echo "✅ Wrapper is working";
echo "\nMode: " . $wrapper->getMode();
$wrapper->destroy();
?>
```

### Verify Metadata Operations

```php
<?php
$image = 'test.jpg';
$wrapper = ImagickWrapper::load($image);

echo "Testing metadata operations:\n";

if (!$wrapper->hasError()) {
  // Test read
  $xmp = $wrapper->getImageProfile('xmp');
  echo ($xmp ? "✅ Can read XMP\n" : "⚠️ Cannot read XMP\n");

  // Test dimensions
  $w = $wrapper->getImageWidth();
  echo ($w > 0 ? "✅ Can read dimensions\n" : "❌ Cannot read dimensions\n");

  // Test properties
  $props = $wrapper->getImageProperties();
  echo (count($props) > 0 ? "✅ Can read properties\n" : "⚠️ No properties found\n");
}

$wrapper->destroy();
?>
```

---

**Note:** All examples assume proper error handling. In production, always check for errors and handle exceptions appropriately.
