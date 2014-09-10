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

define('MAX_PIXEL_DIST', 255 * 255 * 255);

$msx_palette = array(
	array(0,0,0),
	array(0,0,0),
	array(33,200,66),
	array(94,220,120),
	array(84,85,237),
	array(125,118,252),
	array(212,82,77),
	array(66,235,245),
	array(252,85,84),
	array(255,121,120),
	array(212,193,84),
	array(230,206,128),
	array(33,176,59),
	array(201,91,186),
	array(204,204,204),
	array(255,255,255)
);

function
clamp($v)
{
	return ($v < 0) ? 0 : (($v > 255) ? 255 : $v);
}

function
pixel_clamp(&$p) {
	$v = $p[0];
	$p[0] = ($v < 0) ? 0 : (($v > 255) ? 255 : $v);
	$v = $p[1];
	$p[1] = ($v < 0) ? 0 : (($v > 255) ? 255 : $v);
	$v = $p[2];
	$p[2] = ($v < 0) ? 0 : (($v > 255) ? 255 : $v);
}

function
pixel_clamp_rgb(&$r, &$g, &$b) {
	$r = ($r < 0) ? 0 : (($r > 255) ? 255 : $r);
	$g = ($g < 0) ? 0 : (($g > 255) ? 255 : $g);
	$b = ($b < 0) ? 0 : (($b > 255) ? 255 : $b);
}

function
pixel_split($v) {
	return array($v >> 16, $v >> 8 & 255, $v & 255);
}

function
pixel_dist($p1, $p2)
{
	$r = $p2[0] - $p1[0];
	$g = $p2[1] - $p1[1];
	$b = $p2[2] - $p1[2];
	return $r * $r + $g * $g + $b * $b;
} 

function
pixel_add(&$p, &$q, $amount, $scale)
{
	$p[0] = clamp($p[0] + floor($q[0] * $amount / $scale));
	$p[1] = clamp($p[1] + floor($q[1] * $amount / $scale));
	$p[2] = clamp($p[2] + floor($q[2] * $amount / $scale));
}

function
image_to_truecolor(&$input) {
	$ow = imagesx($input);
	$oh = imagesy($input);
	$output = imagecreatetruecolor($ow, $oh);
	imagecopy($output, $input, 0, 0, 0, 0, $ow, $oh);
	return $output;
}

function 
image_crop_middle(&$input, $w, $h, $bgcolor = 0) {
	$ow = imagesx($input);
	$oh = imagesy($input);
	$output = imagecreatetruecolor($w, $h);
	imagefilledrectangle($output, 0, 0, $w, $h, $bgcolor);
	$x = ($w - $ow) >> 1;
	$y = ($h - $oh) >> 1;
	$px = $py = 0;
	if ($y < 0) {
		$oh += $y << 1;
		$py -= $y;
		$y = 0;
	} else {
		$h -= $y << 1;
	}
	if ($x < 0) {
		$ow += $x << 1;
		$px -= $x;
		$x = 0;
	} else {
		$w -= $x << 1;
	}
	imagecopyresized($output, $input, $x, $y, $px, $py, $w, $h, $ow, $oh);
	return $output;
}

function
image_scale_width($input, $w) {
	$ow = imagesx($input);
	$oh = imagesy($input);
	$h = floor($oh * $w / $ow);
	$output = imagecreatetruecolor($w, $h);
	imagecopyresampled($output, $input, 0, 0, 0, 0, $w, $h, $ow, $oh);	
	return $output;
}

function
image_scale_height($input, $h) {
	$ow = imagesx($input);
	$oh = imagesy($input);
	$w = floor($ow * $h / $oh);
	$output = imagecreatetruecolor($w, $h);
	imagecopyresampled($output, $input, 0, 0, 0, 0, $w, $h, $ow, $oh);	
	return $output;
}

function image_scale_restrict($input, $w, $h) {
	$ow = $nw = imagesx($input);
	$oh = $nh = imagesy($input);
	if ($nw > $w) {
		$nh *= $w / $nw;
		$nw = $w;
	}
	if ($nh > $h) {
		$nw *= $h / $nh;
		$nh = $h;
	}
	$output = imagecreatetruecolor($nw, $nh);
	imagecopyresampled($output, $input, 0, 0, 0, 0, $nw, $nh, $ow, $oh);	
	return $output;
}

function
image_scale_stretch($input, $w, $h) {
	$ow = imagesx($input);
	$oh = imagesy($input);
	$output = imagecreatetruecolor($w, $h);
	imagecopyresampled($output, $input, 0, 0, 0, 0, $w, $h, $ow, $oh);	
	return $output;
}

function image_median(&$img) {
	$output = image_to_truecolor($img);
	$w = imagesx($img)-1;
	$h = imagesy($img)-1;
	for ($nx = 1; $nx < $w; $nx++) {
		for ($ny = 1;$ny < $h; $ny++) {
			$px00 = imagecolorat($img,$nx-1,$ny-1);
			$px01 = imagecolorat($img,$nx  ,$ny-1);
			$px02 = imagecolorat($img,$nx+1,$ny-1);			
			$px10 = imagecolorat($img,$nx-1,$ny);
			$px11 = imagecolorat($img,$nx  ,$ny);
			$px12 = imagecolorat($img,$nx+1,$ny);			
			$px20 = imagecolorat($img,$nx-1,$ny+1);
			$px21 = imagecolorat($img,$nx  ,$ny+1);
			$px22 = imagecolorat($img,$nx+1,$ny+1);	
			
			$pr = array($px00>>16 & 255, $px01>>16 & 255, $px02>>16 & 255, $px10>>16 & 255, $px11>>16 & 255, $px12>>16 & 255, $px20>>16 & 255, $px21>>16 & 255, $px22>>16 & 255);
			$pg = array($px00>>8 & 255, $px01>>8 & 255, $px02>>8 & 255, $px10>>8 & 255, $px11>>8 & 255, $px12>>8 & 255, $px20>>8 & 255, $px21>>8 & 255, $px22>>8 & 255);
			$pb = array($px00 & 255, $px01 & 255, $px02 & 255, $px10 & 255, $px11 & 255, $px12 & 255, $px20 & 255, $px21 & 255, $px22 & 255);
			sort($pr); sort($pg); sort($pb);
			$nr = $pr[count($pr) >> 1];
			$ng = $pg[count($pg) >> 1];
			$nb = $pb[count($pb) >> 1];			
			$nrgb = ($nr<<16) + ($ng<<8) + $nb;
			if (!imagesetpixel($output, $nx, $ny, $nrgb)) 
				return FALSE;
		}
	}
	return $output;
}

function image_convolution(&$img, $mat, $div = 1, $off = 0) {
	if (!imageistruecolor($img) || 
		!is_array($mat) || 
		count($mat)!=3 || 
		count($mat[0])!=3 || 
		count($mat[1])!=3 || 
		count($mat[2])!=3)
	return FALSE;
	$output = image_to_truecolor($img);
	$w = imagesx($img)-1;
	$h = imagesy($img)-1;
	for ($nx = 1; $nx < $w; $nx++) {
		for ($ny = 1; $ny < $h; $ny++) {
			$px00 = imagecolorat($img,$nx-1,$ny-1);
			$px01 = imagecolorat($img,$nx  ,$ny-1);
			$px02 = imagecolorat($img,$nx+1,$ny-1);			
			$px10 = imagecolorat($img,$nx-1,$ny);
			$px11 = imagecolorat($img,$nx  ,$ny);
			$px12 = imagecolorat($img,$nx+1,$ny);
			$px20 = imagecolorat($img,$nx-1,$ny+1);
			$px21 = imagecolorat($img,$nx  ,$ny+1);
			$px22 = imagecolorat($img,$nx+1,$ny+1);				

			$nr = $mat[0][0]*($px00>>16 & 255) + $mat[0][1]*($px01>>16 & 255) + $mat[0][2]*($px02>>16 & 255) + $mat[1][0]*($px10>>16 & 255) + $mat[1][1]*($px11>>16 & 255) + $mat[1][2]*($px12>>16 & 255) + $mat[2][0]*($px20>>16 & 255) + $mat[2][1]*($px21>>16 & 255) + $mat[2][2]*($px22>>16 & 255);
			$nr = intval(round($nr / $div) + $off);
			$nr = ($nr < 0) ? 0 : (($nr > 255) ? 255 : $nr);
			$ng = $mat[0][0]*($px00>>8 & 255)  + $mat[0][1]*($px01>>8 & 255) + $mat[0][2]*($px02>>8 & 255) + $mat[1][0]*($px10>>8 & 255) + $mat[1][1]*($px11>>8 & 255) + $mat[1][2]*($px12>>8 & 255) + $mat[2][0]*($px20>>8 & 255) + $mat[2][1]*($px21>>8 & 255) + $mat[2][2]*($px22>>8 & 255);
			$ng = intval(round($ng / $div) + $ofs);
			$ng = ($ng < 0) ? 0 : (($ng > 255) ? 255 : $ng);
			$nb = $mat[0][0]*($px00 & 255) + $mat[0][1]*($px01 & 255) + $mat[0][2]*($px02 & 255) + $mat[1][0]*($px10 & 255) + $mat[1][1]*($px11 & 255) + $mat[1][2]*($px12 & 255) + $mat[2][0]*($px20 & 255) + $mat[2][1]*($px21 & 255) + $mat[2][2]*($px22 & 255);
			$nb=intval(round($nb / $div) + $ofs);
			$nb = ($nb < 0) ? 0 : (($nb > 255) ? 255 : $nb);			
			$nrgb=($nr<<16)+($ng<<8)+$nb;
			if (!imagesetpixel($output, $nx, $ny, $nrgb)) 
				return FALSE;
		}
	}
	return $output;
}

function tile_get(&$img, $ox, $oy) {
	$values = '';
	for ($y = 0; $y < 8; $y++) {
		for ($x = 0; $x < 8; $x++) {
			$values .= chr(imagecolorat($img, $ox + $x, $oy + $y));
		}
	}
	return $values;
}

function tile_put(&$img, $ox, $oy, $values) {
	$i = 0;
	for ($y = 0; $y < 8; $y++) {
		for ($x = 0; $x < 8; $x++) {
			$c = ord(substr($values, $i++, 1));
			imagesetpixel($img, $ox + $x, $oy + $y, $c);
		}
	}	
}

function image_to_tiles(&$input, &$cnt) {
	$tiles = array();
	$chars = array();

	$cnt = 0;
	$w = imagesx($input);
	$h = imagesy($input);
	for ($y = 0; $y < $h; $y += 8) {
		for ($x = 0; $x < $w; $x += 8) {
			$values = tile_get($input, $x, $y);
			if (!isset($tiles[$values])) {
				$chars[] = $cnt;
				$tiles[$values] = $cnt++;
			} else {
				$chars[] = $tiles[$values];
			}
		}
	}

	imagefilledrectangle($input, 0, 0, $w, $h, 0);

	$x = 0;
	$y = 0;
	$tiles = array_flip($tiles);
	for ($i = 0; $i < $cnt; $i++) {
		tile_put($input, $x, $y, $tiles[$i]);
		$x += 8;
		if ($x >= $w) {
			$x = 0;
			$y += 8;
		}
	}
	
	return $chars;
}

class Surf {
	var $width;
	var $height;
	var $palette_type = 'msx1';
	var $palette_rgb;
	var $palette_333;
	var $palette_size = 16;
	var $sections = array();
	
	function create(&$img, $mode) {
		$w = imagesx($img);
		$h = imagesy($img);
		switch ($mode) {
			case 2:
				$surf = new Surf2($w, $h);
				break;
			case 5:
				$surf = new Surf5($w, $h);
				break;
			case 6:
				$surf = new Surf6($w, $h);
				break;
			case 7:
				$surf = new Surf7($w, $h);
				break;
			case 8:
				$surf = new Surf8($w, $h);
				break;
			case 12:
				$surf = new Surf12($w, $h);
				break;
		}
		$surf->setPalette('msx1');
		return $surf;
	}
	
	function fromImage(&$img, $dither = true, $cb = NULL) {
		if ($this->palette_type == 'adaptive') {
			$this->findPalette($img, $dither);
		}
		return ($dither) ? $this->fromImageDither($img, $cb) : $this->fromImageDirect($img, $cb);
	}
	
	function setPalette($type, $palette_333 = NULL) {
		$this->palette_type = $type;
		switch ($type) {
			case 'msx1':
				global $msx_palette;
				$this->palette_rgb = $msx_palette;
				$this->makePalette333();			
				break;
			case 'msx2':
				global $msx_palette;
				$this->palette_rgb = $msx_palette;
				$this->makePalette333();
				$this->makePaletteRGB(); // FIXME: pre-calculate
				break;
			case 'custom':
				$this->palette_333 = $palette_333;
				$this->makePaletteRGB();				
				break;				
		}
	}
	
	function _sort_palette_cb($a, $b) {
		$a = $a[0] * $a[0] + $a[1] * $a[1] + $a[2] * $a[2];
		$b = $b[0] * $b[0] + $b[1] * $b[1] + $b[2] * $b[2];
		return $a - $b;
	}
	
	function _findPalette(&$input, $dither, $numcolors) {
		/* This is a hack to use libGDs color quantizer to our purposes */
		/* Since the quantizer doesn't suport 3-3-3 bits, we are iteratively guessing the number of colors, */
		/* until we get very few duplicates from the 8-8-8 quantizer */
		
		/* Our guess is well-educated though, using the bissection algorithm */
		$try = 0;
		$best = 0;
		$lower = $numcolors;
		$upper = 512;
		while ($try++ < 16 && $best != $numcolors) {
			$ncolors = ($lower + $upper) >> 1;
			$copy = image_to_truecolor($input);
			imagetruecolortopalette($copy, $dither, $ncolors);
			$dups = 0;
			$pal = array();
			for ($i = 0; $i < $ncolors; $i++) {
				$c = @imagecolorsforindex($copy, $i);
				$hash = (($c['red'] & 224) << 1) | 
					(($c['green'] & 224) >> 2) |
					(($c['blue'] & 224) >> 5);
				if ($pal[$hash])
					$dups++;
				$pal[$hash]++;
			}
			imagedestroy($copy);
			$total = $ncolors - $dups;
			//echo "$try: $ncolors - $dups = $total <br />";
			if ($total > $numcolors) {
				$upper = $ncolors;				
			} else
			if ($total < $numcolors) {
				$lower = $ncolors;
			}			
			if ($total > $best && $total <= $numcolors) {
				$best = $total;
				$bestPal = $pal;
			}
		}
		
		$this->palette_333 = array_fill(0, $numcolors, array(0,0,0));
		$i = 0;
		foreach ($bestPal as $hash => $dummy) {
			$this->palette_333[$i++] = array(
				$hash >> 6, 
				($hash >> 3) & 7,
				$hash & 7
			);
		}
		
		usort($this->palette_333, array($this, '_sort_palette_cb'));
		
		$this->makePaletteRGB();
		$this->palette_type = 'custom';
	}
	
	function findPalette(&$input, $dither) {
		return $this->_findPalette(&$input, $dither, $this->palette_size);
	}
	
	function makePalette333() {
		$palette = array();
		foreach ($this->palette_rgb as $c) {
			$r = $c[0] >> 5;
			$g = $c[1] >> 5;
			$b = $c[2] >> 5;
			$palette[] = array($r, $g, $b);
		}
		$this->palette_333 = $palette;
	}
	
	function makePaletteRGB() {
		$palette = array();
		foreach ($this->palette_333 as $c) {
			$r = floor($c[0] * 255 / 7);
			$g = floor($c[1] * 255 / 7);
			$b = floor($c[2] * 255 / 7);		
			$palette[] = array($r, $g, $b);
		}
		$this->palette_rgb = $palette;
	}
	
	function _outputBinary() {}
	
	function outputBinary($from = 0, $size = NULL) {
		ob_start();
		$this->_outputBinary();
		$bin = ob_get_clean();
		
		$size = ($size === NULL) ? strlen($bin) : $size;
		$end = $size + $from - 1;
		
		$head = chr(0xFE) . chr($from & 255) . chr(($from >> 8) & 255) .
			chr($end & 255) . chr(($end >> 8) & 255) .
			chr($from & 255) . chr(($from >> 8) & 255);
		
		echo $head;
		echo substr($bin, $from, $size);
	}
}

include 'scr2.class.php';
include 'scr5.class.php';
include 'scr6.class.php';
include 'scr7.class.php';
include 'scr8.class.php';
include 'scr12.class.php';

?>
