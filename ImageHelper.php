<?php
namespace lamhq\php\helpers;

/**
 * @author Lam Huynh <lamhq.com>
 */
class ImageHelper {

	/**
	 * Resize image to extract dimension but keep the ratio
	 * @author Lam Huynh
	 *
	 * @param string $src absolute file name to the source image file
	 * @param string $dst absolute file name to the destination image file
	 * @param array $options the options in terms of name-value pairs. The following options are specially handled:
	 * - width: int, width of destination image
	 * - height: int, height of destination image
	 * - fit: bool, whether to fill the gap with bgColor. if not, crop the edge of source image to fill the dimension
	 * - watermarkFile: string, file path to watermark file
	 * - bgColor: string, hexa color code for padding
	 */
	static public function resize($src, $dst, $options=[]) {
		if ( !file_exists(dirname($dst)) )
			mkdir(dirname($dst), 0777, true);

		// load setting
		$setting = array_merge(array(
			'width' => null,
			'height' => null,
			'fit' => true,
			'watermarkFile' => null,
			'bgColor' => '#ffffff'
		), $options);
		extract($setting);
		list($r, $g, $b) = self::hexToRGB($bgColor);

		// load image from disk
		$type = exif_imagetype($src);
		switch ($type) {
			case IMAGETYPE_GIF:
				$old = imagecreatefromgif($src);
				break;
			case IMAGETYPE_JPEG:
				$old = imagecreatefromjpeg($src);
				break;
			case IMAGETYPE_PNG:
				$old = imagecreatefrompng($src);
				break;
			default:
				return false;
				break;
		}

		// auto rotate image
		$exif = exif_read_data($src);
		if (isset($exif['Orientation'])) {
			$color = imagecolorallocate($old, $r, $g, $b);
			switch($exif['Orientation']) {
				case 3:
					$old = imagerotate($old,180,$color);
					break;
				case 6:
					$old = imagerotate($old,-90,$color);
					break;
				case 8:
					$old = imagerotate($old,90,$color);
					break;
			}
		}

		// resize image
		$ow = imagesx($old);
		$oh = imagesy($old);
		$w = $width;
		$h = $height;
		if (!$w && !$h) {
			$w = $ow;
			$h = $oh;
		} elseif (!$w) {
			$w = $ow * $h / $oh;
		} elseif (!$h) {
			$h = $oh * $w / $ow;
		}
		$new = imagecreatetruecolor($w, $h);
		$color = imagecolorallocate($new, $r, $g, $b);
		imagefill($new, 0, 0, $color);
		if ($fit) {
			// fit image to extract dimension (add padding)
			if (($ow / $oh) >= ($w / $h)) {
				$nw = $w;
				$nh = $w * ($oh / $ow);
				$nx = 0;
				$ny = round(abs($h - $nh) / 2);
			} else {
				$nh = $h;
				$nw = $h * ($ow / $oh);
				$nx = round(abs($w - $nw) / 2);
				$ny = 0;
			}
			imagecopyresampled($new, $old, $nx, $ny, 0, 0, $nw, $nh, $ow, $oh);
		} else {
			// fill image to extract dimension (crop)
			if (($ow / $oh) >= ($w / $h)) {
				$nh = $h;
				$nw = $ow * ($ow / $oh);
				$ox = round(abs($w - $nw) / 2);
				$oy = 0;
			} else {
				$nw = $w;
				$nh = $nw * ($oh / $ow);
				$oy = round(abs($h - $nh) / 2);
				$ox = 0;
			}
			imagecopyresampled($new, $old, 0, 0, $ox, $oy, $nw, $nh, $ow, $oh);
		}

		// add watermark to source image
		if ($watermarkFile)
			self::addWatermark($new, $watermarkFile);

		// save image to disk
		switch ($type) {
			case IMAGETYPE_GIF:
				$old = imagegif($new, $dst);
				break;
			case IMAGETYPE_JPEG:
				$old = imagejpeg($new, $dst);
				break;
			case IMAGETYPE_PNG:
				$old = imagepng($new, $dst);
				break;
			default:
				break;
		}
		return true;
	}

	/**
	 * Add watermark to image resource
	 *
	 * @author Lam Huynh
	 * @param resource $image
	 * @param string $watermarkFile file path to watermark file. only support png
	 */
	static protected function addWatermark($image, $watermarkFile) {
		if (!is_file($watermarkFile)) return false;
		$watermark = imagecreatefrompng($watermarkFile);

		// calculate watermark size to make it always viewable
		$wWidth = min(imagesx($image)/3, imagesx($watermark));
		$wHeight = $wWidth * imagesy($watermark) / imagesx($watermark);

		// calculate watermark position to make it center the image
		$dst_x = (imagesx($image) - $wWidth) / 2;
		$dst_y = (imagesy($image) - $wHeight) / 2;

		// Copy the stamp image onto our photo using the margin offsets and the photo
		// width to calculate positioning of the stamp.
		imagecopyresampled($image, $watermark,
			$dst_x, $dst_y, 0, 0,
			$wWidth, $wHeight, imagesx($watermark), imagesy($watermark));
		return true;
	}

	static protected function hexToRGB($hex) {
		list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
		return [$r, $g, $b];
	}

}
