<?php

/* some configuration keys with accepted values */

// screen mode
$options['screen'] = 2; // 2, 5, 6, 7, 8, 12

// tile mode (screen 2 only)
$options['tile'] = false; // true, false

// compensate for screen resolution
$options['compensation'] = true; // true, false

// scale mode
$options['scale'] = 'auto'; // 'auto', 'width', 'height', 'stretch'

// scale size (for scale width or height only)
//$options['scale_size'] = 256;

// use error diffusion
$options['floyd'] = true; // true, false

// palette type
$options['palette_type'] = 'adaptive'; // 'msx1', 'msx2', 'adaptive'

// output files to the given directory
$options['output_path'] = '.'; // any directory

// output VRAM ranges from dump
/*
$options['ranges'] = array(
	'0,4096',
	'8192,4096'	
);
*/

// denoise filter
$options['denoise'] = false; // true, false

// edges filter
$options['edges'] = false; // true, false

?>
