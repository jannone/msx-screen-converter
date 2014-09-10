<?php

/*
	Copyright 2007 Rafael de Oliveira Jannone

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	
*/

require_once 'msxconv.inc.php';

class Surf6 extends Surf {
	var $pixels;
	
	function Surf6($w, $h) {
		$w = $w & ~3;
		$sz = $w * $h / 4;
		$this->width = $w;
		$this->height = $h;
		$this->pixels = array_fill(0, $sz, 0);
		$this->palette_size = 4;
	}	
	
	function fromImageDirect(&$img, $cb = NULL) {
		$w = imagesx($img);
		$h = imagesy($img);

		$w = $this->width;
		$h = $this->height;

		$msx = imagecreate(1,1);
		for ($i = 0; $i < 4; $i++) {
			$c = $this->palette_rgb[$i];
			imagecolorallocate($msx, $c[0], $c[1], $c[2]);
		}
		
		$addr = 0;
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));		
			for ($x = 0; $x < $w; $x+=4) {
				$v0 = imagecolorat($img, $x + 0, $y);
				$v1 = imagecolorat($img, $x + 1, $y);
				$v2 = imagecolorat($img, $x + 2, $y);
				$v3 = imagecolorat($img, $x + 3, $y);
				
				$v0 = imagecolorclosest($msx, ($v0 >> 16) & 0xFF, ($v0 >> 8) & 0xFF, $v0 & 0xFF);
				$v1 = imagecolorclosest($msx, ($v1 >> 16) & 0xFF, ($v1 >> 8) & 0xFF, $v1 & 0xFF);
				$v2 = imagecolorclosest($msx, ($v2 >> 16) & 0xFF, ($v2 >> 8) & 0xFF, $v2 & 0xFF);
				$v3 = imagecolorclosest($msx, ($v3 >> 16) & 0xFF, ($v3 >> 8) & 0xFF, $v3 & 0xFF);
				
				$this->pixels[$addr] = chr(($v0 << 6) | ($v1 << 4) | ($v2 << 2) | $v3);
				++$addr;
			}
		}
		$this->pixels = implode('', $this->pixels);
		
		imagedestroy($msx);
	}

	function fromImageDither(&$img, $cb = NULL) {
		$w = imagesx($img);
		$h = imagesy($img);
		$ow = $this->width;
		$oh = $this->height;
		
		$msx = imagecreate(1,1);
		for ($i = 0; $i < 4; $i++) {
			$c = $this->palette_rgb[$i];
			imagecolorallocate($msx, $c[0], $c[1], $c[2]);
		}
		
		$addr = 0;		
	
		/* error propagation line buffers */
		$dither_sz = $ow + 2;
		$dither_line1 = array_fill(0, $dither_sz, array(0,0,0));
		$dither_line2 = array_fill(0, $dither_sz, array(0,0,0));

		/* init first line buffer */
		if ($oh > 0) {
			for ($i=0; $i < $ow; $i++)
				$dither_line1[$i + 1] = pixel_split(ImageColorAt($img, $i, 0));
		}
		
		for ($y = 0; $y < $oh; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $oh));		
			if ($y < $oh - 1) {
				for ($i=0; $i < $ow; $i++)
					$dither_line2[$i + 1] = pixel_split(ImageColorAt($img, $i, $y + 1));
			}
			$byte = 0;
			for ($x = 0; $x < $ow; $x++) {
				$v = $dither_line1[$x+1];
				$c = $v;
				pixel_clamp($c);

				$c = imagecolorclosest($msx, $v[0], $v[1], $v[2]);
				$_v = imagecolorsforindex($msx, $c);
				
				$e = array($v[0] - $_v['red'], $v[1] - $_v['green'], $v[2] - $_v['blue']);

				$p = &$dither_line1[$x+1];
				$p[0] += $e[0] * 7 >> 4;
				$p[1] += $e[1] * 7 >> 4;
				$p[2] += $e[2] * 7 >> 4;
				
				$p = &$dither_line2[$x];
				$p[0] += $e[0] * 3 >> 4;
				$p[1] += $e[1] * 3 >> 4;
				$p[2] += $e[2] * 3 >> 4;

				$p = &$dither_line2[$x+1];
				$p[0] += $e[0] * 5 >> 4;
				$p[1] += $e[1] * 5 >> 4;
				$p[2] += $e[2] * 5 >> 4;
									
				$p = &$dither_line2[$x+2];							
				$p[0] += $e[0] >> 4;
				$p[1] += $e[1] >> 4;
				$p[2] += $e[2] >> 4;
				
				$pos = $x & 3;
				// this looks dumb but it's probably faster than calculating the shifts
				switch ($pos) {
					case 0:
						$byte = $c << 6;
						break;
					case 1:
						$byte |= $c << 4;
						break;
					case 2:
						$byte |= $c << 2;
						break;
					case 3:
						$byte |= $c;
						$this->pixels[$addr] = chr($byte);
						++$addr;
						break;
				}
			}
			/* swap line buffers (avoiding reallocation) */
			$swap = $dither_line1;
			$dither_line1 = $dither_line2;
			$dither_line2 = $swap;
		}
		
		$this->pixels = implode('', $this->pixels);
		
		imagedestroy($msx);
	}
	
	function preview()
	{
		$w = $this->width;
		$h = $this->height;
		$output = imagecreatetruecolor($w, $h);
		
		$addr = 0;
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x+=4, $addr++) {
				$v = ord(substr($this->pixels, $addr, 1));
				$v0 = $v >> 6;
				$v1 = ($v >> 4) & 3;
				$v2 = ($v >> 2) & 3;
				$v3 = ($v) & 3;
				
				$c = $this->palette_rgb[$v0];
				imagesetpixel($output, $x+0, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
				$c = $this->palette_rgb[$v1];
				imagesetpixel($output, $x+1, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
				$c = $this->palette_rgb[$v2];
				imagesetpixel($output, $x+2, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
				$c = $this->palette_rgb[$v3];
				imagesetpixel($output, $x+3, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
			}
		}
		$output = image_scale_stretch($output, $w / 2, $h);
		
		return $output;
	}	
	
	function _outputBinary() {
		$file = fopen("php://output", "wb");

		$sz = $this->width * $this->height / 4;
		fputs($file, $this->pixels, $sz);
		fclose($file);
	}
}

?>
