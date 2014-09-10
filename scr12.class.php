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

class Surf12 extends Surf {
	var $pixels;
	
	function Surf12($w, $h) {
		$this->width = $w & (~3);
		$this->height = $h;
		$sz = $this->width * $this->height;
		$this->pixels = array_fill(0, $sz, 0);
	}
	
	function fromImageDirect(&$img, $cb = NULL) {
		$w = imagesx($img);
		$h = imagesy($img);
		
		$addr = 0;		
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));
			for ($x = 0; $x < $w; $x += 4) {
				$rgb = imagecolorat($img, $x+0, $y);
				$r0 = ($rgb >> 16) & 0xFF;
				$g0 = ($rgb >> 8) & 0xFF;
				$b0 = $rgb & 0xFF;
				$r0 >>= 3; $g0 >>= 3; $b0 >>= 3;

				$rgb = imagecolorat($img, $x+1, $y);
				$r1 = ($rgb >> 16) & 0xFF;
				$g1 = ($rgb >> 8) & 0xFF;
				$b1 = $rgb & 0xFF;
				$r1 >>= 3; $g1 >>= 3; $b1 >>= 3;

				$rgb = imagecolorat($img, $x+2, $y);
				$r2 = ($rgb >> 16) & 0xFF;
				$g2 = ($rgb >> 8) & 0xFF;
				$b2 = $rgb & 0xFF;
				$r2 >>= 3; $g2 >>= 3; $b2 >>= 3;

				$rgb = imagecolorat($img, $x+3, $y);
				$r3 = ($rgb >> 16) & 0xFF;
				$g3 = ($rgb >> 8) & 0xFF;
				$b3 = $rgb & 0xFF;
				$r3 >>= 3; $g3 >>= 3; $b3 >>= 3;
				
				// RGB mean 
				$rm = round(($r0 + $r1 + $r2 + $r3) / 4);
				$bm = round(($b0 + $b1 + $b2 + $b3) / 4);
				$gm = round(($g0 + $g1 + $g2 + $g3) / 4);
				
				// YJK mean
				$ym = round($bm/2 + $rm/4 + $gm/8);
				$ym = ($ym < 0) ? 0 : (($ym > 31) ? 31 : $ym);
				$j = $rm - $ym;
				$k = $gm - $ym;
				$j = ($j < -32) ? -32 : (($j > 31) ? 31 : $j);
				$k = ($k < -32) ? -32 : (($k > 31) ? 31 : $k);
				
				// individual Y
				$y0 = round($b0/2 + $r0/4 + $g0/8);
				$y1 = round($b1/2 + $r1/4 + $g1/8);
				$y2 = round($b2/2 + $r2/4 + $g2/8);
				$y3 = round($b3/2 + $r3/4 + $g3/8);
				$y0 = ($y0 < 0) ? 0 : (($y0 > 31) ? 31 : $y0);
				$y1 = ($y1 < 0) ? 0 : (($y1 > 31) ? 31 : $y1);
				$y2 = ($y2 < 0) ? 0 : (($y2 > 31) ? 31 : $y2);
				$y3 = ($y3 < 0) ? 0 : (($y3 > 31) ? 31 : $y3);				
				
				$this->pixels[$addr++] = chr(($y0 << 3) | ($k & 7));
				$this->pixels[$addr++] = chr(($y1 << 3) | (($k >> 3) & 7));
				$this->pixels[$addr++] = chr(($y2 << 3) | ($j & 7));
				$this->pixels[$addr++] = chr(($y3 << 3) | (($j >> 3) & 7));
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
		
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));
				
			if ($y < $oh - 1) {
				for ($i=0; $i < $ow; $i++)
					$dither_line2[$i + 1] = pixel_split(ImageColorAt($img, $i, $y + 1));
			}
				
			for ($x = 0; $x < $w; $x += 4) {
				// RGB mean
				$v0 = $dither_line1[$x+1];
				$v1 = $dither_line1[$x+2];
				$v2 = $dither_line1[$x+3];
				$v3 = $dither_line1[$x+4];
				$rm = ($v0[0] + $v1[0] + $v2[0] + $v3[0]) >> 2;
				$gm = ($v0[1] + $v1[1] + $v2[1] + $v3[1]) >> 2;
				$bm = ($v0[2] + $v1[2] + $v2[2] + $v3[2]) >> 2;
				pixel_clamp_rgb(&$rm, &$gm, &$bm);
				$rm >>= 3; $gm >>= 3; $bm >>= 3;
				
				// YJK mean
				$ym = round($bm/2 + $rm/4 + $gm/8);
				$ym = ($ym < 0) ? 0 : (($ym > 31) ? 31 : $ym);
				$j = $rm - $ym;
				$k = $gm - $ym;
				$j = ($j < -32) ? -32 : (($j > 31) ? 31 : $j);
				$k = ($k < -32) ? -32 : (($k > 31) ? 31 : $k);
				$djk = $j/2 + $k/4;			

				// JK scattered order
				$jk = array($k & 7, ($k >> 3) & 7, $j & 7, ($j >> 3) & 7);
				
				// individual Y	
				$v = array($v0, $v1, $v2, $v3);
				for ($i=0; $i<4; $i++) {
					$c = $v[$i];
					$_c = $c;
					pixel_clamp($c);
					$r = $c[0] >> 3;
					$g = $c[1] >> 3;
					$b = $c[2] >> 3;
					$yl = round($b/2 + $r/4 + $g/8);
					$yl = ($yl < 0) ? 0 : (($yl > 31) ? 31 : $yl);
					$this->pixels[$addr++] = chr(($yl << 3) | $jk[$i]);
					
					// make projection to calculate error
					$_r = floor(($yl + $j) * 255 / 31);
					$_g = floor(($yl + $k) * 255 / 31);
					$_b = floor((5*$yl/4 - $djk) * 255 / 31);
					pixel_clamp_rgb($_r, $_g, $_b);	
					
					// propagate
					$e = array($_c[0] - $_r, $_c[1] - $_g, $_c[2] - $_b);
					
					$p = &$dither_line1[$i+$x+1];
					$p[0] += $e[0] * 7 >> 4;
					$p[1] += $e[1] * 7 >> 4;
					$p[2] += $e[2] * 7 >> 4;
					
					$p = &$dither_line2[$i+$x];
					$p[0] += $e[0] * 3 >> 4;
					$p[1] += $e[1] * 3 >> 4;
					$p[2] += $e[2] * 3 >> 4;

					$p = &$dither_line2[$i+$x+1];
					$p[0] += $e[0] * 5 >> 4;
					$p[1] += $e[1] * 5 >> 4;
					$p[2] += $e[2] * 5 >> 4;
										
					$p = &$dither_line2[$i+$x+2];							
					$p[0] += $e[0] >> 4;
					$p[1] += $e[1] >> 4;
					$p[2] += $e[2] >> 4;					
				}
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
			for ($x = 0; $x < $w; $x += 4, $addr += 4) {
				$v0 = ord(substr($this->pixels, $addr+0, 1));
				$v1 = ord(substr($this->pixels, $addr+1, 1));
				$v2 = ord(substr($this->pixels, $addr+2, 1));
				$v3 = ord(substr($this->pixels, $addr+3, 1));
								
				$k = (($v1 & 7) << 3) | ($v0 & 7);
				$j = (($v3 & 7) << 3) | ($v2 & 7);
				$k = ($k > 31) ? $k - 64 : $k;
				$j = ($j > 31) ? $j - 64 : $j;
				$djk = $j/2 + $k/4;				
				
				$y0 = ($v0 >> 3);
				$r0 = floor(($y0 + $j) * 255 / 31);
				$g0 = floor(($y0 + $k) * 255 / 31);
				$b0 = floor((5*$y0/4 - $djk) * 255 / 31);
				pixel_clamp_rgb($r0, $g0, $b0);

				$y1 = ($v1 >> 3);
				$r1 = floor(($y1 + $j) * 255 / 31);
				$g1 = floor(($y1 + $k) * 255 / 31);
				$b1 = floor((5*$y1/4 - $djk) * 255 / 31);
				pixel_clamp_rgb($r1, $g1, $b1);

				$y2 = ($v2 >> 3);
				$r2 = floor(($y2 + $j) * 255 / 31);
				$g2 = floor(($y2 + $k) * 255 / 31);
				$b2 = floor((5*$y2/4 - $djk) * 255 / 31);
				pixel_clamp_rgb($r2, $g2, $b2);

				$y3 = ($v3 >> 3);
				$r3 = floor(($y3 + $j) * 255 / 31);
				$g3 = floor(($y3 + $k) * 255 / 31);
				$b3 = floor((5*$y3/4 - $djk) * 255 / 31);
				pixel_clamp_rgb($r3, $g3, $b3);
				
				imagesetpixel($output, $x+0, $y, ($r0 << 16) | ($g0 << 8) | $b0);
				imagesetpixel($output, $x+1, $y, ($r1 << 16) | ($g1 << 8) | $b1);
				imagesetpixel($output, $x+2, $y, ($r2 << 16) | ($g2 << 8) | $b2);
				imagesetpixel($output, $x+3, $y, ($r3 << 16) | ($g3 << 8) | $b3);
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
