<?php

/*
* Color Indexer
* Find first match of specific color
**/

function indexPixels($img)
{
	$original_ 				= new Imagick($img);
	$height					= 0;
	$width					= 0;
	list($width, $height)	= getimagesize($img);

	for( $x = 0 ; $x < $width ; $x++ )
	{
		for( $y = 0 ; $y < $height ; $y++ )
		{
			$matrix_org[$x][$y]		= $original_->getImagePixelColor($x, $y)->getColorAsString();
		}
	}
	
	return $matrix_org;
}

function findPixel($pixelIndex, $r, $g, $b, $tolerance=5)
{
	$width			= count($pixelIndex);
	$height			= count($pixelIndex[0]);

	for( $x = 0 ; $x < $width ; $x++ )
	{
		for( $y = 0 ; $y < $height ; $y++ )
		{
			$colors 				= preg_replace('/[^-,0-9+$]/', '', $pixelIndex[$x][$y]); 
			$colors					= explode(',', $colors);
			$r_org					= $colors[0];
			$g_org					= $colors[1];
			$b_org					= $colors[2];
			
			if( 	( $r <= ($r_org+$tolerance) && $r >= ($r_org - $tolerance) ) 
				&&  ( $g <= ($g_org+$tolerance) && $g >= ($g_org - $tolerance) ) 
				&&  ( $b <= ($b_org+$tolerance) && $b >= ($b_org - $tolerance) ) )
			{
				return array( $x, $y );
			}
		}
	}

	return false;
}

function track($files, $r, $g, $b, $tolerance=5)
{
	$black 		= array();

	foreach($files as $file)
	{
		$black[]	= findPixel($file, 0, 0, 0, $tolerance);
	}
	
	return $black;
}

//Program begin

//Load all images
$files		= glob("/home/media_generator/tests/php/tracking/*{.png}", GLOB_BRACE);
sort($files);
$start = time();
echo "\nTracking...\n";
foreach( $files as $file )
{
	$index	= indexPixels($file);
	echo "\n\nBlack dot: ";
	$value  = findPixel($index, 0, 0, 0);
	echo "{$value[0]}, {$value[1]}";
	echo "\nRed dot: ";
	$value  = findPixel($index, 255, 0, 0);
	echo "{$value[0]}, {$value[1]}";	
	echo "\nBlue dot: ";
	$value  = findPixel($index, 0, 0, 255);
	echo "{$value[0]}, {$value[1]}";

}
$end	= time() - $start;
echo "\n\nTracked three dots in {$end} seconds...";