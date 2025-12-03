# ImageMagick Fallback Implementation - Summary

## Objective Completed ✅

Successfully implemented **fallback support for external ImageMagick** command-line tools when the PHP Imagick extension is not available.

## What Was Implemented

### 1. New Wrapper Class: `ImagickWrapper`

**File:** `lib/imagick_wrapper.php`

A unified interface that automatically:
- Attempts to use PHP Imagick extension (if available)
- Falls back to external ImageMagick CLI tools (`convert`, `identify`)
- Returns clear error messages if neither is available

**Key Methods:**
- `ImagickWrapper::load($image_path)` - Factory method to create wrapper
- `hasError()` - Check if initialization failed
- `getError()` - Get error message
- `getImageProfile($profile_name)` - Read XMP, IPTC, etc.
- `setImageProfile($profile_name, $data)` - Write metadata
- `getImageWidth() / getImageHeight()` - Get image dimensions
- `writeImage()` - Save changes

### 2. Modified Core Files

#### `lib/metadata_reader.php`
- Changed from: `new Imagick($path)`
- Changed to: `ImagickWrapper::load($path)`
- Result: Now works with external ImageMagick if PHP extension missing

#### `lib/metadata_writer.php`
- Changed from: `new Imagick($path)` with `extension_loaded('imagick')` check
- Changed to: `ImagickWrapper::load($path)` with error handling
- Result: Automatic fallback support for metadata writing

#### `main.inc.php`
- Added: `require_once(FACETAGWRITE_PATH . 'lib/imagick_wrapper.php');`
- Updated: `face_tag_write_extract_xmp()` to use wrapper
- Updated: `face_tag_write_get_xmp()` error handling for extraction
- Result: Entire plugin now supports fallback mechanism

## How It Works

### Priority Order:
1. **Check PHP Imagick extension** → if loaded, use native extension
2. **Check external ImageMagick CLI** → if available, use command-line tools
3. **Error** → if neither available, return clear error message

### Supported Operations:
- ✅ Reading XMP metadata
- ✅ Reading IPTC metadata
- ✅ Writing XMP metadata
- ✅ Writing IPTC metadata
- ✅ Reading image dimensions
- ✅ Getting image properties

### External Commands Used:
- `identify` - Read image dimensions and properties
- `convert` - Extract and inject metadata profiles

## Installation & Requirements

### For Servers WITH PHP Imagick:
✅ **No changes needed** - Plugin works exactly as before

### For Servers WITHOUT PHP Imagick:
Install external ImageMagick tools:

**Debian/Ubuntu:**
```bash
apt-get update
apt-get install imagemagick
```

**CentOS/RedHat:**
```bash
yum install ImageMagick
```

**macOS:**
```bash
brew install imagemagick
```

**Windows:**
Download from: https://imagemagick.org/script/download.php#windows

## Testing

### Automated Test Script:
Run `test_imagick_fallback.php` to verify:
- PHP Imagick extension status
- External ImageMagick availability
- Wrapper functionality with sample image

### Manual Testing:
1. Open a photo in the Piwigo admin
2. Click "Taguer" (Tag Faces) button
3. Check logs in `plugins/face_tag_editor/face_tag_editor_debug.log` for:
   - `INFO: Using PHP Imagick extension` (native method)
   - `INFO: Using external ImageMagick command-line tools` (fallback)

## Files Changed

```
face_tag_editor/
├── lib/
│   ├── imagick_wrapper.php          [NEW] Wrapper class
│   ├── metadata_reader.php           [MODIFIED] Use wrapper
│   └── metadata_writer.php           [MODIFIED] Use wrapper
├── main.inc.php                      [MODIFIED] Load wrapper, use fallback
├── CHANGELOG_IMAGEMAGICK.md          [NEW] Detailed changelog
├── IMPLEMENTATION_SUMMARY.md         [NEW] This file
└── test_imagick_fallback.php         [NEW] Test script
```

## Backward Compatibility

✅ **Fully backward compatible**
- No breaking changes
- Existing code continues to work unchanged
- Seamless fallback is transparent to developers

## Performance Impact

- **PHP Imagick:** No change (same as before)
- **External ImageMagick:** Minimal impact
  - Slight delay due to CLI process spawning (~50-200ms per operation)
  - For typical usage (one image at a time), impact is negligible
  - Not recommended for batch processing of thousands of images

## Error Handling

Clear error messages in three scenarios:

1. **PHP Imagick available & works:** ✅ Uses native extension
2. **External ImageMagick available:** ✅ Uses CLI tools
3. **Neither available:** ❌ Clear error message explaining solutions

## Troubleshooting

### "Neither PHP Imagick nor external ImageMagick is available"

**Solution 1: Install PHP Imagick extension**
```bash
pecl install imagick
php -m | grep imagick  # Verify installation
```

**Solution 2: Install external ImageMagick**
See "Installation & Requirements" section above

### External ImageMagick commands not found

**Check if convert is in PATH:**
```bash
which convert          # Linux/Mac
where convert          # Windows
```

**Verify it's not disabled in php.ini:**
```
disable_functions = ...  # Should NOT contain 'exec' or 'passthru'
```

## Future Enhancements

Possible improvements:
- Cache wrapper choice to avoid repeated checks
- Support for GraphicsMagick as additional fallback
- Support for exiftool for metadata
- Performance optimization for batch operations
- Configuration option to prefer external over PHP

## Support & Documentation

- Main changelog: `CHANGELOG_IMAGEMAGICK.md`
- Test script: `test_imagick_fallback.php`
- Debug logs: `plugins/face_tag_editor/face_tag_editor_debug.log`

## Version Info

- **Version:** 1.5B
- **Date:** December 3, 2025
- **Plugin:** face_tag_editor for Piwigo
- **Compatibility:** PHP 5.3+, Piwigo 2.8+

---

**Implementation Status:** ✅ COMPLETE

The plugin now works on servers with:
- ✅ PHP Imagick extension installed
- ✅ External ImageMagick CLI tools installed
- ✅ Both available (uses native extension for better performance)

**The plugin will provide clear error messages if neither is available.**
