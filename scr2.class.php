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

class Surf2 extends Surf {
	var $attr;
	var $shape;
	
	function Surf2($w, $h) {
		$sz = ceil($w * $h / 8);
		$this->width = $w & (~7);
		$this->height = $h & (~7);
		$this->attr = array_fill(0, $sz, 0);
		$this->shape = array_fill(0, $sz, 0);
		
		$this->sections = array(
			'shapes' => array(0, 6144),
			'chars' => array(6144, 768),
			'attributes' => array(8192, 6144)
		);
	}
	/*
	function fromImageDirect(&$img, &$palette, $cb = NULL) {
		$msx = imagecreate(1,1);
		foreach ($palette as $rgb) {
			imagecolorallocate($msx, $rgb[0], $rgb[1], $rgb[2]);
		}
		$byte = imagecreate(1,1);
		
		$w = imagesx($img);
		$h = imagesy($img);
		
		$output = new Surf2($w, $h);
		$output->palette = &$palette;
		
		$addr = 0;		
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));
			for ($x = 0; $x < $w; $x += 8) {
				// find most frequent colors
				$freq = array();
				for ($i = 0; $i < 8; $i++) {
					$v = imagecolorat($img, $x + $i, $y);
					$v = imagecolorsforindex($img, $v);
					$v = imagecolorclosest($msx, $v['red'], $v['green'], $v['blue']);
					$freq[$v]++;
				}
				arsort($freq);
				
				// now we have the 2 most frequent colors
				$attrs = array_keys($freq);
				if (count($attrs) == 1)
					$attrs[] = $attrs[0];
					
				// use the 'byte' image to allocate 2 colors and match the 8 pixels
				@imagecolordeallocate($byte, 0);
				@imagecolordeallocate($byte, 1);
				for ($i = 0; $i < 2; $i++) {
					$v = imagecolorsforindex($msx, $attrs[$i]);
					$v = array($v['red'], $v['green'], $v['blue']);
					imagecolorallocate($byte, $v[0], $v[1], $v[2]);
				}
				
				// build the shape+attrs byte
				$b = 128;
				$shape = 0;
				for ($i = 0; $i < 8; $i++) {
					$v = imagecolorat($img, $x + $i, $y);
					$v = imagecolorsforindex($img, $v);
					$v = imagecolorclosest($byte, $v['red'], $v['green'], $v['blue']);
					$shape |= ($v ? $b : 0);
					$b >>= 1;
				}
				$output->attr[$addr] = chr(($attrs[1] << 4) | $attrs[0]);
				$output->shape[$addr] = chr($shape);
				++$addr;				
			}
		}
		$output->attr = implode('', $output->attr);
		$output->shape = implode('', $output->shape);
		return $output;
	}
	*/
	function fromImageDirect(&$img, $cb = NULL) {
		$palette = $this->palette_rgb;
				
		$msx = imagecreate(1,1);
		foreach ($palette as $rgb) {
			imagecolorallocate($msx, $rgb[0], $rgb[1], $rgb[2]);
		}
		$byte = imagecreate(1,1);
		
		$w = imagesx($img);
		$h = imagesy($img);
		
		$addr = 0;		
		for ($y = 0; $y < $h; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $h));
			for ($x = 0; $x < $w; $x += 8) {
				// find most frequent colors
				$freq = array();
				for ($i = 0; $i < 8; $i++) {
					$v = imagecolorat($img, $x + $i, $y);
					$v = imagecolorclosest($msx, ($v >> 16) & 0xFF, ($v >> 8) & 0xFF, $v & 0xFF);
					$freq[$v]++;
				}
				arsort($freq);
				
				// we need at least 2 colors
				$attrs = array_keys($freq);
				if (count($attrs) == 1)
					$attrs[] = $attrs[0];
					
				$best_e = MAX_PIXEL_DIST;
					
				// test combinations
				for ($v1 = 0; $v1 < count($attrs); $v1++) {
					$c1 = $attrs[$v1];
					for ($v2 = $v1 + 1; $v2 < count($attrs); $v2++) {
						$c2 = $attrs[$v2];
						
						// use the 'byte' image to allocate 2 colors and match the 8 pixels
						@imagecolordeallocate($byte, 0);
						@imagecolordeallocate($byte, 1);
						$v = imagecolorsforindex($msx, $c1);
						$a1 = array($v['red'], $v['green'], $v['blue']);
						imagecolorallocate($byte, $a1[0], $a1[1], $a1[2]);
						$v = imagecolorsforindex($msx, $c2);
						$a2 = array($v['red'], $v['green'], $v['blue']);
						imagecolorallocate($byte, $a2[0], $a2[1], $a2[2]);
						
						// build the shape+attrs byte, find error
						$e = 0;
						$b = 128;
						$shape = 0;
						for ($i = 0; $i < 8; $i++) {
							$v = imagecolorat($img, $x + $i, $y);
							$v = array(($v >> 16) & 0xFF, ($v >> 8) & 0xFF, $v & 0xFF);
							$p = imagecolorclosest($byte, $v[0], $v[1], $v[2]);
							$pp = imagecolorsforindex($byte, $p);
							$pp = array($p['red'], $p['green'], $p['blue']);
							$dist = pixel_dist($v, $pp);
							$e += $dist;
							if ($e >= $best_e)
								break;
							$shape |= ($p ? $b : 0);
							$b >>= 1;							
						}
						if ($e < $best_e) {
							$best_e = $e;
							$best_c1 = $c1;
							$best_c2 = $c2;
							$best_shape = $shape;
						}
					}
				}
					
				$this->attr[$addr] = chr(($best_c2 << 4) | $best_c1);
				$this->shape[$addr] = chr($best_shape);
				++$addr;				
			}
		}
		$this->attr = implode('', $this->attr);
		$this->shape = implode('', $this->shape);
	}

	function fromImageDitherLowQuality($input, $cb = NULL) {
		$palette = $this->palette_rgb;
		$first_color = ($this->palette_type == 'msx1') ? 1 : 0;
		
		$msx = imagecreate(1,1);
		foreach ($palette as $c) {
			imagecolorallocate($msx, $c[0], $c[1], $c[2]);
		}		
		
		/* 
			this conversion uses floyd-steinberg error diffusion
			if you're not certain of how it works, check this link:
			http://www.visgraf.impa.br/Courses/ip00/proj/Dithering1/floyd_steinberg_dithering.html
		*/

		/* output pointers */
		$dest_shape = 0;
		$dest_attr = 0;
		
		/* init output surface */
		$w = imagesx($input);
		$h = imagesy($input);

		/* error propagation line buffers */
		$dither_jmp = ($w + 2); 
		$dither_sz = $dither_jmp; // sizeof(pixel)
		$dither_line1 = array_fill(0, $dither_sz, array(0,0,0));
		$dither_line2 = array_fill(0, $dither_sz, array(0,0,0));

		/* init first line buffer */
		if ($this->height > 0) {
			$ow = $this->width;
			for ($i=0; $i < $ow; $i++)
				$dither_line1[$i + 1] = pixel_split(ImageColorAt($input, $i, 0));
		}
		
		$oh = $this->height;
		for ($y = 0; $y < $oh; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $oh));		

			/* buffer line ahead, we'll need those neighbour pixels later */
			if ($y < $oh - 1) {
				for ($i=0; $i < $ow; $i++)
					$dither_line2[$i + 1] = pixel_split(ImageColorAt($input, $i, $y + 1));
			}		
			
			$src = 0;		
			for ($x = 0; $x < $ow; $x += 8, $src += 8, $dest_shape++, $dest_attr++) {
			
				$g8 = imagecreatetruecolor(8, 1);
				for ($i = 0; $i < 8; $i++) {
					$c = $dither_line1[$x + $i + 1];
					pixel_clamp($c);
					imagesetpixel($g8, $i, 0, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
				}
				imagetruecolortopalette($g8, true, 8);
				
				$c1 = @imagecolorsforindex($g8, 0);
				$c2 = @imagecolorsforindex($g8, 1);

				imagedestroy($g8);
				
				$c1 = imagecolorclosest($msx, $c1['red'], $c1['green'], $c1['blue']);
				$c2 = imagecolorclosest($msx, $c2['red'], $c2['green'], $c2['blue']);
				
				/* get the RGB color of bits 0 and 1 */
				$bit0 = &$palette[$c1];				
				$bit1 = &$palette[$c2];

				/* let's generate the bitmask using those colors */
				
				/* reset current shape */
				$v = 0;
						
				/* for each of the 8 horizontal pixels... */
				$bp = 128; /* bit pointer */					
				for ($b = 0; $b < 8; $b++, $bp >>= 1) 
				{
					$dpos = $x + $b + 1; /* dither buffer position */
				
					/* ...we must decide for bit 0 (off) or bit 1 (on) */

					/* get pixel */
					$dv1 = &$dither_line1[$dpos];
					
					/* clamp pixel (inlined to be faster) */
					$_v = $dv1[0];
					$dv1[0] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
					$_v = $dv1[1];
					$dv1[1] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
					$_v = $dv1[2];
					$dv1[2] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
					
					/* calculate distance from source to each considered color */ 
					$_r1 = $dv1[0] - $bit0[0];
					$_g1 = $dv1[1] - $bit0[1];
					$_b1 = $dv1[2] - $bit0[2];
					$d1 = $_r1 * $_r1 + $_g1 * $_g1 + $_b1 * $_b1;
					
					$_r2 = $dv1[0] - $bit1[0];
					$_g2 = $dv1[1] - $bit1[1];
					$_b2 = $dv1[2] - $bit1[2];
					$d2 = $_r2 * $_r2 + $_g2 * $_g2 + $_b2 * $_b2;
					
					/* evaluate the closest pixel */
					if ($d1 < $d2) {
						/* bit0 wins */
						//$v |= 0;
					} else {
						/* bit1 wins */
						/* activate bit */
						$v |= $bp;
					}

					if ($d1 < $d2) {
						$bit = &$bit0;
						$e = array($_r1, $_g1, $_b1);
					} else {
						$bit = &$bit1;
						$e = array($_r2, $_g2, $_b2);
					}
					
					// propagate error to the right neighbour pixel
					$p = &$dither_line1[$dpos];
					$p[0] += $e[0] * 7 >> 4;
					$p[1] += $e[1] * 7 >> 4;
					$p[2] += $e[2] * 7 >> 4;
					
					$p = &$dither_line2[$dpos - 1];
					$p[0] += $e[0] * 3 >> 4;
					$p[1] += $e[1] * 3 >> 4;
					$p[2] += $e[2] * 3 >> 4;

					$p = &$dither_line2[$dpos];
					$p[0] += $e[0] * 5 >> 4;
					$p[1] += $e[1] * 5 >> 4;
					$p[2] += $e[2] * 5 >> 4;
										
					$p = &$dither_line2[$dpos + 1];
					$p[0] += $e[0] >> 4;
					$p[1] += $e[1] >> 4;
					$p[2] += $e[2] >> 4;					
				}
							
				/* output winner combo */
				$this->shape[$dest_shape] = chr($v);
				$this->attr[$dest_attr] = chr(($c2 << 4) | $c1);	/* combine attributes */
			}
			
			/* swap line buffers (avoiding reallocation) */
			$swap = $dither_line1;
			$dither_line1 = $dither_line2;
			$dither_line2 = $swap;
			
			/* the "ahead line" containing the propagated errors, is now the primary line (source) */
		}
		$this->attr = implode('', $this->attr);
		$this->shape = implode('', $this->shape);
		
		imagedestroy($msx);
	}

	function fromImageDither($input, $cb = NULL) {
		$palette = $this->palette_rgb;
		$first_color = ($this->palette_type == 'msx1') ? 1 : 0;
		
		/* 
			this conversion uses floyd-steinberg error diffusion
			if you're not certain of how it works, check this link:
			http://www.visgraf.impa.br/Courses/ip00/proj/Dithering1/floyd_steinberg_dithering.html
		*/

		/* output pointers */
		$dest_shape = 0;
		$dest_attr = 0;
		
		/* init output surface */
		$w = imagesx($input);
		$h = imagesy($input);

		/* error propagation line buffers */
		$dither_jmp = ($w + 2); 
		$dither_sz = $dither_jmp; // sizeof(pixel)
		//dither_line1 = (pixel*)malloc(dither_sz);
		$dither_line1 = array_fill(0, $dither_sz, array(0,0,0));
		//dither_line2 = (pixel*)malloc(dither_sz);
		$dither_line2 = array_fill(0, $dither_sz, array(0,0,0));

		/* init first line buffer */
		if ($this->height > 0) {
			$ow = $this->width;
			for ($i=0; $i < $ow; $i++)
				$dither_line1[$i + 1] = pixel_split(ImageColorAt($input, $i, 0));
		}
		
		$oh = $this->height;
		for ($y = 0; $y < $oh; $y++) {
			if ($cb && $y % 10 == 0)
				call_user_func($cb, intval($y * 100/ $oh));		

			/* buffer line ahead, we'll need those neighbour pixels later */
			if ($y < $oh - 1) {
				for ($i=0; $i < $ow; $i++)
					$dither_line2[$i + 1] = pixel_split(ImageColorAt($input, $i, $y + 1));
			}		
			
			$src = 0;		
			for ($x = 0; $x < $ow; $x += 8, $src += 8, $dest_shape++, $dest_attr++) {
				/* shape value and colors [current combo] */
				/*
				byte v;		
				byte c1, c2;
				pixel dit1[10];
				pixel dit2[10];
				*/

				/* shape value and colors [winner combo] */
				/*
				byte wv;		
				byte wc1, wc2;
				pixel wdit1[10];
				pixel wdit2[10];
				*/			
				
				/* distance from the source pixel */
				$dist = MAX_PIXEL_DIST;
				
				/* our objective is to find the combo (v / c1 / c2) that is closer 
					to our source pixels on the image */
				
				/* considering all possible combinations of colors */
				$dit1_bkp = array_slice($dither_line1, $src, 10);
				for ($c1 = $first_color; $c1 < 16; $c1++) {
					/* get the RGB color of bit 1 */			
					$bit0 = &$palette[$c1];				
					
					/* avoiding duplicate combinations */
					for ($c2 = $c1 + 1; $c2 < 16; $c2++) 
					{				
						/* get the RGB color of bit 1 */
						$bit1 = &$palette[$c2];

						/* init micro-dither buffer, this one will be restored
							for each combination */
						$dit1 = $dit1_bkp;

						/* let's generate the bitmask using those colors */
						
						/* distance between generated pixel and source pixel */
						$pdist = 0;
						
						/* reset current shape */
						$v = 0;
						
						/* error list */
						$el = array();
						
						/* for each of the 8 horizontal pixels... */
						$bp = 128; /* bit pointer */					
						for ($b = 1; $b < 9; $b++, $bp >>= 1) 
						{
							/* ...we must decide for bit 0 (off) or bit 1 (on) */

							/* get pixel */
							$dv1 = &$dit1[$b];
							
							/* clamp pixel (inlined to be faster) */
							$_v = $dv1[0];
							$dv1[0] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
							$_v = $dv1[1];
							$dv1[1] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
							$_v = $dv1[2];
							$dv1[2] = ($_v < 0) ? 0 : (($_v > 255) ? 255 : $_v);
							
							/* calculate distance from source to each considered color */ 
							$_r1 = $dv1[0] - $bit0[0];
							$_g1 = $dv1[1] - $bit0[1];
							$_b1 = $dv1[2] - $bit0[2];
							$d1 = $_r1 * $_r1 + $_g1 * $_g1 + $_b1 * $_b1;
							
							$_r2 = $dv1[0] - $bit1[0];
							$_g2 = $dv1[1] - $bit1[1];
							$_b2 = $dv1[2] - $bit1[2];
							$d2 = $_r2 * $_r2 + $_g2 * $_g2 + $_b2 * $_b2;
							
							/* evaluate the closest pixel */
							if ($d1 < $d2) {
								/* bit0 wins */
								$pdist += $d1;
							} else {
								/* bit1 wins */
								/* activate bit */
								$v |= $bp;						
								$pdist += $d2;
							}
							if ($pdist >= $dist)
								break;
							//if ($floyd) {
								// calculate error of the chosen color 
								if ($d1 < $d2) {
									$bit = &$bit0;
									$e = array($_r1, $_g1, $_b1);
								} else {
									$bit = &$bit1;
									$e = array($_r2, $_g2, $_b2);
								}
								
								// propagate error to the right neighbour pixel
								$p = &$dit1[$b+1];
								$p[0] += $e[0] * 7 >> 4;
								$p[1] += $e[1] * 7 >> 4;
								$p[2] += $e[2] * 7 >> 4;
								
								$el[] = $e;
							//}
						}
						
						/* is this combo closer to the source pixels? */
						if ($pdist < $dist) {
							/* set as the current winner */
							$dist = $pdist;
							$wv = $v; 
							$wc1 = $c1; 
							$wc2 = $c2;
							$wdit1 = $dit1;
							$wel = $el;
						}				
					}
				}
							
				/* output winner combo */
				$this->shape[$dest_shape] = chr($wv);
				$this->attr[$dest_attr] = chr(($wc2 << 4) | $wc1);	/* combine attributes */

				$wdit2 = array_slice($dither_line2, $src, 10);
				//if ($floyd) {
					/* once decided the 8 bits, we should propagate errors to the line below as well */
					for ($b = 1; $b < 9; $b++) {
						$e = $wel[$b-1];

						$p = &$wdit2[$b-1];
						$p[0] += $e[0] * 3 >> 4;
						$p[1] += $e[1] * 3 >> 4;
						$p[2] += $e[2] * 3 >> 4;

						$p = &$wdit2[$b];
						$p[0] += $e[0] * 5 >> 4;
						$p[1] += $e[1] * 5 >> 4;
						$p[2] += $e[2] * 5 >> 4;
											
						$p = &$wdit2[$b+1];							
						$p[0] += $e[0] >> 4;
						$p[1] += $e[1] >> 4;
						$p[2] += $e[2] >> 4;
					}
				//}
				
				/* update line buffers */
				$cnt = count($wdit1);			
				for ($i=0; $i < $cnt; $i++) {
					$dither_line1[$i + $src] = $wdit1[$i];
					$dither_line2[$i + $src] = $wdit2[$i];
				}
				//array_splice($dither_line1, $src, $cnt, $wdit1);
				//array_splice($dither_line2, $src, $cnt, $wdit2);
			}
			
			/* swap line buffers (avoiding reallocation) */
			$swap = $dither_line1;
			$dither_line1 = $dither_line2;
			$dither_line2 = $swap;
			
			/* the "ahead line" containing the propagated errors, is now the primary line (source) */
		}
		$this->attr = implode('', $this->attr);
		$this->shape = implode('', $this->shape);
	}

	/* converts msx screen 2 surface to RGB surface */
	function preview()
	{
		$palette = $this->palette_rgb;

		$src_shape = 0;
		$src_attr = 0;
		$w8 = floor($this->width / 8);
		
		$output = imagecreatetruecolor($this->width, $this->height);
		
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x < $w8; $x++, $src_shape++, $src_attr++, $dest += 8) {
				$v = ord(substr($this->shape, $src_shape, 1));
				$at = ord(substr($this->attr, $src_attr, 1));
				$c1 = $at & 15;
				$c2 = $at >> 4;
				
				$bit0 = &$palette[$c1];
				$bit1 = &$palette[$c2];			
				
				$ix = $x * 8;
				for ($b = 0; $b < 8; $b++) {
					$bit = ($v & 128) ? $bit1 : $bit0;
					imagesetpixel($output, $ix + $b, $y, ($bit[0] << 16) | ($bit[1] << 8) | $bit[2]);
					$v <<= 1;
				}
			}
		}
		
		return $output;
	}
	
	function _outputBinary() {
		$file = fopen("php://output", "wb");
	
		$w = $this->width;
		$h = $this->height;
		$w8 = $w >> 3;

		/* write shape (VRAM @ 0) */

		/* for each row of 8 pixels */
		for ($r = 0; $r < $h; $r += 8)  {
			$base = $r * $w8;
			
			/* for each column of 8 pixels */
			for ($x = 0; $x < $w; $x += 8) {
				$v = $base;				
				/* write block */
				for ($y = 0; $y < 8; $y++, $v += $w8) {
					fputs($file, substr($this->shape, $v, 1), 1);
				}
				++$base;
			}
		}

		/* cover space between shape and attributes */
		if ($this->tilemap) {
			foreach ($this->tilemap as $val) {
				fputs($file, chr($val), 1);
			}
		} else {
			for ($r = 0; $r < 3; $r++) {
				for ($x = 0; $x < 256; $x++)
					fputs($file, chr($x), 1);
			}
		}
		for ($r = 0; $r < 32; $r++) {
			fputs($file, chr(0xD1) . chr(0) . chr($r) . chr(0x0F), 4);
		}
		for ($r = 0; $r < 1152; $r++)
			fputs($file, chr(0), 1);

		/* write attributes (VRAM @ 8192) */

		/* for each row of 8 pixels */
		for ($r = 0; $r < $h; $r += 8) {
			$base = $r * $w8;

			/* for each column of 8 pixels */
			for ($x = 0; $x < $w; $x += 8)
			{
				$v = $base;
				
				/* write block */
				for ($y = 0; $y < 8; $y++, $v += $w8) {
					fputs($file, substr($this->attr, $v, 1), 1);
				}
				++$base;
			}
		}
		fclose($file);
	}
}

?>
