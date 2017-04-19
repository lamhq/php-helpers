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
		if ($type==IMAGETYPE_JPEG) {
			$exif = @exif_read_data($src);
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
		}

		// resize image
		$ow = imagesx($old);
		$oh = imagesy($old);
		$nw = $width;
		$nh = $height;
		// auto calculate the missing width or height in parameters
		if (!$nw && !$nh) {
			$nw = $ow;
			$nh = $oh;
		} elseif (!$nw) {
			$nw = $nh * $ow / $oh;
		} elseif (!$nh) {
			$nh = $nw * $oh / $ow;
		}
		$new = imagecreatetruecolor($nw, $nh);
		$color = imagecolorallocate($new, $r, $g, $b);
		imagefill($new, 0, 0, $color);
		if ($fit) {
			// fit image to extract dimension (add padding)
			$sx = 0;
			$sy = 0;
			$sw = $ow;
			$sh = $oh;
			// landscape to portrait
			if (($sw / $sh) >= ($nw / $nh)) {
				$dw = $nw;
				$dh = $nw * ($sh / $sw);
				$dx = 0;
				$dy = round(abs($nh - $dh) / 2);
			} else {
				$dw = $nh * ($sw / $sh);
				$dh = $nh;
				$dx = round(abs($nw - $dw) / 2);
				$dy = 0;
			}
		} else {
			// fill image to extract dimension (crop)
			$dx = 0;
			$dy = 0;
			$dw = $nw;
			$dh = $nh;
			// landscape to portrait
			if (($ow / $oh) >= ($dw / $dh)) {
				$sw = $oh * ($dw / $dh);
				$sh = $oh;
				$sx = round(abs($ow - $sw) / 2);
				$sy = 0;
			} else {
				$sw = $ow;
				$sh = $ow * ($dh / $dw);
				$sx = 0;
				$sy = round(abs($oh - $sh) / 2);
			}
		}
		imagecopyresampled($new, $old, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);

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
