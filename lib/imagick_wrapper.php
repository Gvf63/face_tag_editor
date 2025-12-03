<?php
/**
 * ImageMagick Wrapper - Fallback support for PHP Imagick extension
 *
 * This wrapper provides a unified interface for:
 * 1. PHP Imagick extension (if available)
 * 2. External ImageMagick command-line tools (convert, identify) as fallback
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class ImagickWrapper
{
  private $image_path;
  private $use_external = false;
  private $error = null;
  private $imagick = null;

  public static function load($image_path)
  {
    return new self($image_path);
  }

  private function __construct($image_path)
  {
    $this->image_path = $image_path;

    // Try PHP Imagick extension first
    if (extension_loaded('imagick')) {
      try {
        $this->imagick = new Imagick($image_path);
        error_log('INFO: Using PHP Imagick extension');
        return;
      } catch (Exception $e) {
        error_log('WARNING: PHP Imagick extension failed: ' . $e->getMessage());
      }
    }

    // Try external ImageMagick
    if ($this->checkExternalImageMagick()) {
      error_log('INFO: Using external ImageMagick command-line tools');
      $this->use_external = true;
    } else {
      $this->error = 'Neither PHP Imagick extension nor external ImageMagick is available';
      error_log('ERROR: ' . $this->error);
    }
  }

  private function checkExternalImageMagick()
  {
    $commands = array('convert', 'identify', 'magick');

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
        error_log('Found external ImageMagick command: ' . $cmd);
        return true;
      }
    }

    return false;
  }

  public function hasError()
  {
    return $this->error !== null;
  }

  public function getError()
  {
    return $this->error;
  }

  public function getImageProfile($profile_name)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageProfile($profile_name);
      } catch (Exception $e) {
        error_log('Error getting profile via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    } else {
      return $this->getProfileExternal($profile_name);
    }
  }

  private function getProfileExternal($profile_name)
  {
    $temp_profile = tempnam(sys_get_temp_dir(), 'imgprof_');

    try {
      if ($profile_name === 'xmp') {
        $cmd = 'convert ' . escapeshellarg($this->image_path) . ' profile:xmp ' . escapeshellarg($temp_profile);
      } elseif ($profile_name === 'iptc') {
        $cmd = 'convert ' . escapeshellarg($this->image_path) . ' profile:iptc ' . escapeshellarg($temp_profile);
      } else {
        @unlink($temp_profile);
        return false;
      }

      $return_var = 0;
      @exec($cmd, $dummy_output, $return_var);

      if ($return_var === 0 && file_exists($temp_profile) && filesize($temp_profile) > 0) {
        $data = file_get_contents($temp_profile);
        @unlink($temp_profile);
        return $data;
      }

      @unlink($temp_profile);
      return false;

    } catch (Exception $e) {
      error_log('Error getting profile via external ImageMagick: ' . $e->getMessage());
      @unlink($temp_profile);
      return false;
    }
  }

  public function setImageProfile($profile_name, $profile_data)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->setImageProfile($profile_name, $profile_data);
      } catch (Exception $e) {
        error_log('Error setting profile via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    } else {
      return $this->setProfileExternal($profile_name, $profile_data);
    }
  }

  private function setProfileExternal($profile_name, $profile_data)
  {
    $temp_profile = tempnam(sys_get_temp_dir(), 'imgprof_');

    try {
      if (file_put_contents($temp_profile, $profile_data) === false) {
        error_log('Failed to write temporary profile file');
        @unlink($temp_profile);
        return false;
      }

      $backup_image = $this->image_path . '.bak';
      if (!@copy($this->image_path, $backup_image)) {
        error_log('Failed to create backup of image');
        @unlink($temp_profile);
        return false;
      }

      $cmd = 'convert ' . escapeshellarg($this->image_path) .
             ' -profile ' . escapeshellarg($temp_profile) .
             ' ' . escapeshellarg($this->image_path);

      $return_var = 0;
      @exec($cmd, $output, $return_var);

      @unlink($temp_profile);

      if ($return_var !== 0) {
        error_log('External convert command failed to set profile');
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }

      @unlink($backup_image);
      return true;

    } catch (Exception $e) {
      error_log('Error setting profile via external ImageMagick: ' . $e->getMessage());
      @unlink($temp_profile);
      return false;
    }
  }

  public function writeImage($output_path = null)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        if ($output_path === null) {
          $output_path = $this->image_path;
        }
        return $this->imagick->writeImage($output_path);
      } catch (Exception $e) {
        error_log('Error writing image via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    }
    return true;
  }

  public function getImageWidth()
  {
    if ($this->hasError()) {
      return 0;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageWidth();
      } catch (Exception $e) {
        error_log('Error getting image width: ' . $e->getMessage());
        return 0;
      }
    } else {
      return $this->getImageDimensionExternal('width');
    }
  }

  public function getImageHeight()
  {
    if ($this->hasError()) {
      return 0;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageHeight();
      } catch (Exception $e) {
        error_log('Error getting image height: ' . $e->getMessage());
        return 0;
      }
    } else {
      return $this->getImageDimensionExternal('height');
    }
  }

  private function getImageDimensionExternal($dimension)
  {
    $cmd = 'identify -format "%' . ($dimension === 'width' ? 'w' : 'h') . '" ' . escapeshellarg($this->image_path);

    $output = array();
    $return_var = 0;
    @exec($cmd, $output, $return_var);

    if ($return_var === 0 && !empty($output[0])) {
      $value = intval($output[0]);
      return $value > 0 ? $value : 0;
    }

    return 0;
  }

  public function getImageProperties()
  {
    if ($this->hasError()) {
      return array();
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageProperties();
      } catch (Exception $e) {
        error_log('Error getting image properties: ' . $e->getMessage());
        return array();
      }
    }
    return array();
  }

  public function clear()
  {
    if ($this->imagick) {
      try {
        $this->imagick->clear();
      } catch (Exception $e) {
        // Ignore cleanup errors
      }
    }
  }

  public function destroy()
  {
    if ($this->imagick) {
      try {
        $this->imagick->destroy();
      } catch (Exception $e) {
        // Ignore cleanup errors
      }
    }
    $this->imagick = null;
  }

  public function isUsingExternal()
  {
    return $this->use_external;
  }

  public function getMode()
  {
    if ($this->hasError()) {
      return 'error';
    }
    return $this->use_external ? 'external' : 'php-imagick';
  }
}
?>
