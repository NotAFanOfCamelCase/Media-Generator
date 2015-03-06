<?php

/*
* Color Indexer
* Find first match of specific color
**/

function findPixel($img, $r, $g, $b, $tolerance=5)
{
	$original_ 				= new Imagick($img);
	list($width, $height)	= getimagesize($img);

	for( $x = 0 ; $x < $width ; $x++ )
	{
		for( $y = 0 ; $y < $height ; $y++ )
		{
			$curr_color				= $original_->getImagePixelColor($x, $y)->getColorAsString();
			$colors 				= preg_replace('/[^-,0-9+$]/', '', $curr_color); 
			$colors					= explode(',', $colors);
			
			if( 	( $r <= ($colors[0]+$tolerance) && $r >= ($colors[0] - $tolerance) ) 
				&&  ( $g <= ($colors[1]+$tolerance) && $g >= ($colors[1] - $tolerance) ) 
				&&  ( $b <= ($colors[2]+$tolerance) && $b >= ($colors[2] - $tolerance) ) )
			{
				//echo "\nInput: {$r}, {$g}, {$b}, matched to {$colors[0]}, {$colors[1]}, {$colors[2]}";
				return array( $x, $y );
			}
		}
	}

	return false;
}

function track($files, $r, $g, $b, $tolerance=5)
{
	$arr 		= array();

	foreach($files as $file)
	{
		$arr[]	= findPixel($file, $r, $g, $b, $tolerance);
	}
	
	return $arr;
}

//Program begin

//Load all images
$files		= glob("/home/media_generator/tests/php/tracking/*{.png}", GLOB_BRACE);
sort($files);
$start		= time();

echo "\n\nTracking...";
echo "\n\nTracking black dot\n";
foreach(track($files, 0, 0, 0) as $value){
	echo "\n{$value[0]}, {$value[1]}";
}
echo "\n\nTracking red dot\n";
foreach(track($files, 255, 0, 0) as $value){
	echo "\n{$value[0]}, {$value[1]}";
}
echo "\n\nTracking blue dot\n";
foreach(track($files, 0, 0, 255) as $value){
	echo "\n{$value[0]}, {$value[1]}";
}
$end		= time()-$start;
echo "\n\nTracked in {$end} seconds...\n";