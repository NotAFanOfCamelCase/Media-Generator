<?php

require_once '/home/media_generator/lib/php/Tracker.php';
require_once '/home/media_generator/lib/php/Tracking_Data.php';


//Program begin

$tracker	= new Tracker;
$needles	= array('0,0,0', '0,0,255', '255,0,0'); //Colors to look for

//Load all images
$files		= glob("/home/media_generator/tests/php/tracking/*{.png}", GLOB_BRACE);
sort($files);
$start		= time();
$i			= 0;
echo "\n\nTracking...";

$results	= $tracker->track($files, $needles);

foreach( $results as $obj ){
	$obj->save("/home/media_generator/tests/php/data_obj/file{$i}.dat");
	$i++;
}

$end		= time()-$start;
echo "\n\nTracked in {$end} second(s)...\n";