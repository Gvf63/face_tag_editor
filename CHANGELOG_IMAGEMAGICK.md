# ImageMagick Fallback Support - Changelog

## Version 1.5B - ImageMagick Fallback Implementation

### New Feature: External ImageMagick Support

The plugin now supports a **fallback mechanism** to external ImageMagick command-line tools when the PHP Imagick extension is not available.

### What Changed

#### 1. New File: `lib/imagick_wrapper.php`
A new wrapper class that provides unified support for both:
- **PHP Imagick extension** (primary method)
- **External ImageMagick command-line tools** (fallback)

**Features:**
- Automatic detection of available ImageMagick implementations
- Graceful fallback from PHP extension to CLI tools
- Clear error messages when neither is available
- Logging for debugging

**Command-line tools used:**
- `convert` - for reading/writing image metadata (profiles)
- `identify` - for reading image dimensions

#### 2. Modified Files

**`lib/metadata_reader.php`**
- Replaced direct `new Imagick()` call with `ImagickWrapper::load()`
- Now supports both PHP Imagick and external ImageMagick
- Better error handling for missing metadata tools

**`lib/metadata_writer.php`**
- Replaced direct `new Imagick()` call with `ImagickWrapper::load()`
- Automatic fallback if PHP Imagick unavailable
- Maintains all original functionality with either implementation

**`main.inc.php`**
- Added `require_once` for `lib/imagick_wrapper.php`
- Updated `face_tag_write_extract_xmp()` to use wrapper
- Removed hard check for `extension_loaded('imagick')` in `face_tag_write_get_xmp()`
- Improved error handling for extraction failures

### How It Works

1. **Initialize wrapper:** `$imagick = ImagickWrapper::load($image_path);`
2. **Check for errors:** `if ($imagick->hasError()) { ... }`
3. **Use like normal Imagick:** `$profile = $imagick->getImageProfile('xmp');`

### Compatibility

✅ **Works with:**
- PHP Imagick extension (PHP >= 5.3)
- External ImageMagick CLI tools (ImageMagick 6.x, 7.x)

✅ **Gracefully handles:**
- Missing PHP extension but ImageMagick CLI available
- Missing both (clear error message)
- Image reading/writing operations
- XMP and IPTC profile operations

### Installation Requirements

**Minimum requirements unchanged:**
- Piwigo 2.8+
- PHP 5.3+

**New optional requirement for servers without PHP Imagick:**
- ImageMagick CLI tools (`convert`, `identify`)
  - Debian/Ubuntu: `apt-get install imagemagick`
  - CentOS/RedHat: `yum install ImageMagick`
  - macOS: `brew install imagemagick`
  - Windows: Download from https://imagemagick.org/

### Testing

To verify the fallback is working:

1. Check the debug log in `plugins/face_tag_editor/face_tag_editor_debug.log`
2. Look for messages like:
   - `INFO: Using PHP Imagick extension` (native method)
   - `INFO: Using external ImageMagick command-line tools` (fallback)
   - `ERROR: Neither PHP Imagick extension nor external ImageMagick is available` (error)

### Benefits

✅ **Wider server compatibility** - works on shared hosting without PHP Imagick
✅ **No code duplication** - unified interface through wrapper
✅ **Transparent fallback** - developers don't need to worry which method is used
✅ **Better error messages** - clear feedback if neither method is available
✅ **Backward compatible** - no breaking changes to existing functionality

### Performance Notes

- PHP Imagick is faster (native extension)
- External CLI tools slightly slower due to process spawning
- Performance impact is minimal for typical use (one image at a time)
- Profile extraction using CLI tools is efficient with temporary files

### Known Limitations

- External ImageMagick has slightly slower performance than PHP extension
- Requires proper file permissions for temporary file operations
- On Windows, ensure `convert` and `identify` are in system PATH

### Troubleshooting

**Problem:** "Neither PHP Imagick extension nor external ImageMagick is available"

**Solutions:**
1. Check if PHP Imagick is installed: `php -m | grep imagick`
2. If not, install it: `pecl install imagick`
3. If installation fails, install external ImageMagick tools instead
4. On Linux: `apt-get install imagemagick` or `yum install ImageMagick`
5. On macOS: `brew install imagemagick`
6. On Windows: Download from imagemagick.org

**Problem:** External ImageMagick commands not found

**Solution:**
- Ensure `convert` and `identify` are in the system PATH
- Check your server's `php.ini` settings for `disable_functions` directive
- Verify file permissions on temporary directory `_data/tmp`

### Future Enhancements

Possible improvements for future versions:
- Caching wrapper choice (avoid repeated checks)
- Support for other metadata tools (exiftool)
- Performance optimization for batch operations
- GraphicsMagick support as additional fallback

---

**Version:** 1.5B
**Date:** December 2025
**Compatibility:** face_tag_editor plugin for Piwigo
