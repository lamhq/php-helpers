<?php
namespace lamhq\helpers;

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
	 * - pad: bool, whether to fill the gap in destination image with padding. if not, crop the edge of source image to fill the dimension
	 * - watermarkFile: string, file path to watermark file
	 * - bgColor: string, hexa color code for padding
	 */
	static public function resize($src, $dst, $options=[]) {
		if ( is_file($dst) || !is_file($src) ) return false;
		if ( !file_exists(dirname($dst)) )
			mkdir(dirname($dst), 0777, true);

		$setting = array_merge(array(
			'width' => null,
			'height' => null,
			'pad' => true,
			'watermarkFile' => null,
			'bgColor' => '#ffffff'
		), $options);
		extract($setting);
		list($r, $g, $b) = self::hexToRGB($bgColor);

		// load image from disk
		$imageType = pathinfo($src, PATHINFO_EXTENSION);
		switch ($imageType) {
			case 'gif':
				$old = imagecreatefromgif($src);
				break;
			case 'jpg':
			case 'jpeg':
				$old = imagecreatefromjpeg($src);
				break;
			case 'png':
				$old = imagecreatefrompng($src);
				break;
			default:
				return;
				break;
		}
		// auto rotate image
		if ( function_exists('exif_read_data') ) {
			$exif = @exif_read_data($src);
			$color = imagecolorallocate($old, $r, $g, $b);
			if(!empty($exif['Orientation'])) {
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
		}

		// resize image
		$oldWidth = imagesx($old);
		$oldHeight = imagesy($old);
		if (!$width && !$height) {
			$newWidth = $oldWidth;
			$newHeight = $oldHeight;
		} else {
			$newWidth = $width ? $oldWidth*$height/$oldHeight : $width;
			$newHeight = $height ? $oldHeight*$width/$oldWidth : $height;
		}
		$new = imagecreatetruecolor($newWidth, $newHeight);
		$color = imagecolorallocate($new, $r, $g, $b);
		imagefill($new, 0, 0, $color);
		if ($pad) {
			// fit image to extract dimension (add padding)
			if (($oldWidth / $oldHeight) >= ($newWidth / $newHeight)) {
				// by width
				$nw = $newWidth;
				$nh = $oldHeight * ($newWidth / $oldWidth);
				$nx = 0;
				$ny = round(abs($newHeight - $nh) / 2);
			} else {
				// by height
				$nw = $oldWidth * ($newHeight / $oldHeight);
				$nh = $newHeight;
				$nx = round(abs($newWidth - $nw) / 2);
				$ny = 0;
			}
			imagecopyresampled($new, $old, $nx, $ny, 0, 0, $nw, $nh, $oldWidth, $oldHeight);
		} else {
			// fill image to extract dimension (crop)
			if (($oldWidth / $oldHeight) >= ($newWidth / $newHeight)) {
				// by height
				$oh = $oldHeight;
				$ow = $oh * ($newWidth / $newHeight);
				$ox = round(abs($ow - $oldWidth) / 2);  // crop from center
				$oy = 0;
			} else {
				// by width
				$ow = $oldWidth;
				$oh = $ow * ($newHeight / $newWidth);
				// $oy = round(abs($oh - $oldHeight) / 2);  // crop from middle
				$oy = 0;
				$ox = 0;
			}
			imagecopyresampled($new, $old, 0, 0, $ox, $oy, $newWidth, $newHeight, $ow, $oh);
		}

		// add watermark to source image
		if ($watermarkFile)
			self::addWatermark($new, $watermarkFile);

		// save image to disk
		switch ($imageType) {
			case 'gif':
				$old = imagegif($new, $dst);
				break;
			case 'jpg':
			case 'jpeg':
				$old = imagejpeg($new, $dst);
				break;
			case 'png':
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
