#!/usr/bin/php
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

$stderr = fopen("php://stderr", "w");

include 'msxconv.inc.php';

if (!function_exists('file_put_contents')) {
	function file_put_contents($filename, $data) {
		$f = fopen($filename, "wb");
		fwrite($f, $data);
		fclose($f);
	}
}

function error_exit($s) {
	info("{$s}\n");
	exit(1);
}

function info($s) {
	global $stderr;
	fputs($stderr, $s);
	fflush($stderr);
}

function renderPalette(&$output) {
	$from = 0;
	$size = $output->palette_size * 2;
	$end = $size + $from - 1;
		
	$head = chr(0xFE) . chr($from & 255) . chr(($from >> 8) & 255) .
		chr($end & 255) . chr(($end >> 8) & 255) .
		chr($from & 255) . chr(($from >> 8) & 255);
		
	echo $head;
	$pal = $output->palette_333;
	for ($i = 0; $i < $output->palette_size; $i++) {
		$r = $pal[$i][0];
		$g = $pal[$i][1];
		$b = $pal[$i][2];				
		echo chr($r << 4 | $b) . chr($g);
	}	
}

function progress_cb($value) {
	info(".");
}

function convert($file, $options) {
	global $palette;
	
	$input = @imagecreatefrompng($file);
	if (!$input) {
		$input = @imagecreatefromgif($file);
		if ($input)
			$input = image_to_truecolor($input);
	}
	if (!$input) {
		$input = @imagecreatefromjpeg($file);
	}
	if (!$input)
		return "Image type unrecognized";

	$screen = intval($options['screen']);
	switch ($screen) {
		case 2:
		case 5:
		case 6:
		case 7:
		case 8:
		case 12:
			break;
		default:
			$screen = 2;
	}

	/*
	// TODO: disabled for the moment (not quite working)
	$crop_w = $crop_h = 0;
	if ($crop = $options['crop']) {
		$crop_w = intval($options['crop_width']);
		$crop_h = intval($options['crop_height']);		
		if ($crop_w < 0 || $crop_w > 256)
			return "Crop width out of range";
		if ($crop_h < 0 || $crop_h > 192)
			return "Crop height out of range";
	}
	*/
	
	switch ($screen) {
		case 2:
			$crop_w = 256;
			$crop_h = 192;
			break;
		case 5:		
		case 8:
		case 12:
			$crop_w = 256;
			$crop_h = 212;
			break;
		case 7:
		case 6:
			$crop_w = 512;
			$crop_h = 212;
			break;
	}	

	// TODO: implement transformation matrix for all scaling operations
	if ($compensation = $options['compensation'] && $options['scale'] != 'stretch') {
		$w = imagesx($input);
		$h = imagesy($input);
		$ratio = 256 / $crop_w;
		if ($ratio != 1) {
			if ($ratio >= 1) {
				// expand height
				$input = image_scale_stretch($input, $w, $h * $ratio);
			} else {
				// expand width
				$input = image_scale_stretch($input, $w / $ratio, $h);
			}
		}
	}

	if ($scale = $options['scale']) {
		$scale_size = $options['scale_size'];
		switch ($scale) {
			case 'auto':
				$input = image_scale_restrict($input, $crop_w, $crop_h);
				break;
			case 'width': 
				$scale_size = is_numeric($scale_size) ? intval($scale_size) : $crop_w;
				$input = image_scale_width($input, $scale_size);
				break;
			case 'height': 
				$scale_size = is_numeric($scale_size) ? intval($scale_size) : $crop_h;
				$input = image_scale_height($input, $scale_size);
				break;
			case 'stretch':
				$input = image_scale_stretch($input, $crop_w, $crop_h);
				break;
		}
	}

	/*
	// TODO
	$crop_w = ($crop_w == 0) ? imagesx($input) : $crop_w;
	$crop_h = ($crop_h == 0) ? imagesy($input) : $crop_h;
	$crop_w = ($crop_w > 256) ? 256 : $crop_w;
	$crop_h = ($crop_h > 192) ? 192 : $crop_h;
	*/	

	$input = image_crop_middle($input, $crop_w, $crop_h);
	
	$denoise = $options['denoise'];
	if ($denoise) {
		$input = image_median($input);
	}

	$edges = $options['edges'];
	if ($edges) {
		$laplace = array(
			array(-1, -1, -1),
			array(-1, 8, -1),
			array(-1, -1, -1)	
		);
		$input = image_convolution($input, $laplace);
	}

	$tilemap = NULL;
	$tilecount = 0;
	$floyd = $options['floyd'] ? true : false;	
	if ($screen == 2) {
		$tile = $options['tile'];
		if ($tile) {
			imagetruecolortopalette($input, false, 256);
			$tilemap = image_to_tiles($input, $tilecount);
			$input = image_to_truecolor($input);
		}
	}

	$output = Surf::create($input, $screen);
	$output->setPalette($options['palette_type']);
	$output->fromImage($input, $floyd, 'progress_cb');

	$name = basename($file);
	$name = preg_replace('/\..*$/', '', $name);
	$name = strtoupper(substr(preg_replace('/[^0-9a-z_]/i', '', $name), 0, 8));
	$file_ext = ($screen < 10) ? ('.SC' . $screen) : ('.S' . $screen);
	$name = (($name == '') ? 'SCREEN' . $screen : $name) . $file_ext;
	
	$output->filename = $name;
	$output->tilemap = $tilemap;
	$output->tilecount = $tilecount;

	return $output;
}

if (!$argv[1]) {
	error_exit("syntax: {$argv[0]} <file> [config.file]");
}

if (!file_exists($argv[1])) {
	error_exit("error: can't find file {$argv[1]}");
}

$options = array(
	'screen' => 2,
	'compensation' => true,
	'scale' => 'auto',
	'floyd' => true,
	'palette_type' => 'adaptive',
	'output_path' => '.'
);

if ($argv[2]) {
	include ($argv[2]);
}

info("converting {$argv[1]} ");

$output = convert($argv[1], $options);
if (is_string($output)) {
	error_exit("error: {$output}");
}

info("\n");

chdir($options['output_path']);

$ranges = $options['ranges'];

if ($ranges) {
	if (is_string($ranges)) {
		$ranges = array('' => $ranges);
	}
	foreach ($ranges as $key => $range) {
		if ($output->sections[$range]) {
			$range = $output->sections[$range];
		}
		$custom = explode(',', $range);
		$from = intval(@$custom[0]);
		$size = intval(@$custom[1]);
		
		ob_start();
		$output->outputBinary($from, $size);
		$data = ob_get_clean();
		$key = ($key === '') ? '' : ".{$key}";
		file_put_contents("{$output->filename}{$key}", $data);
	}
} else {
	$from = 0;
	$size = NULL;
	ob_start();
	$output->outputBinary($from, $size);
	$data = ob_get_clean();
	file_put_contents($output->filename, $data);	
}


if ($output->palette_type == 'custom') {
	$fpal = preg_replace('/\..*$/U', '.PAL', $output->filename);
	ob_start();
	renderPalette($output);
	$data = ob_get_clean();
	file_put_contents($fpal, $data);
}

exit(0);

?>
