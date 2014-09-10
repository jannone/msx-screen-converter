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

class Surf8 extends Surf {
	var $pixels;
	
	function Surf8($w, $h) {
		$sz = $w * $h;
		$this->width = $w;
		$this->height = $h;
		$this->pixels = array_fill(0, $sz, 0);
	}
	
	function fromImageDirect(&$img, $cb = NULL) {
		$w = imagesx($img);
		$h = imagesy($img);
		
		$addr = 0;		
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));		
			for ($x = 0; $x < $w; $x++) {
				$rgb = imagecolorat($img, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				
				$r >>= 5;
				$g >>= 5;
				$b >>= 6;
				
				$this->pixels[$addr] = chr(($g << 5) | ($r << 2) | $b);
				++$addr;
			}
		}
		$this->pixels = implode('', $this->pixels);
	}
	
	function fromImageDither(&$img, $cb = NULL) {
		$w = imagesx($img);
		$h = imagesy($img);

		$ow = $this->width;
		$oh = $this->height;
		
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
			for ($x = 0; $x < $ow; $x++) {
				$v = $dither_line1[$x+1];
				$c = $v;
				pixel_clamp($c);

				$r = $c[0] >> 5;
				$g = $c[1] >> 5;
				$b = $c[2] >> 6;
				
				$_r = $r * 255 / 7;
				$_g = $g * 255 / 7;
				$_b = $b * 255 / 3;
				
				$e = array($v[0] - $_r, $v[1] - $_g, $v[2] - $_b);

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
				
				$this->pixels[$addr] = chr(($g << 5) | ($r << 2) | $b);
				++$addr;
			}
			/* swap line buffers (avoiding reallocation) */
			$swap = $dither_line1;
			$dither_line1 = $dither_line2;
			$dither_line2 = $swap;
		}
		$this->pixels = implode('', $this->pixels);	
	}
	
	function preview()
	{
		$w = $this->width;
		$h = $this->height;
		$output = imagecreatetruecolor($w, $h);
		
		$addr = 0;
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++, $addr++) {
				$v = ord(substr($this->pixels, $addr, 1));
				$r = floor((($v >> 2) & 7) * 255 / 7);
				$g = floor((($v >> 5) & 7) * 255 / 7);
				$b = floor(($v & 3) * 255 / 3);
				imagesetpixel($output, $x, $y, ($r << 16) | ($g << 8) | $b);
			}
		}
		
		return $output;
	}	
	
	function _outputBinary() {
		$file = fopen("php://output", "wb");
	
		$sz = $this->width * $this->height;
		fputs($file, $this->pixels, $sz);
		fclose($file);		
	}
}

?>
