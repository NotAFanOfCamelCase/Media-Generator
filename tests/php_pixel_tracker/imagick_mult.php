<?php

require_once '/home/media_generator/lib/php/Disk.php';

$disk		= new Disk;
$chars		= array();
$base		= new Imagick('/home/media_generator/tests/php/circle.jpg');
$working_img= NULL;

foreach( $disk->discover_dir('/home/media_generator/tests/php/test_imgs/', 'jpg') as $img ){
	$chars[]	= new Imagick($img);
}

for( $i = 0 ; $i < sizeof($chars) ; $i++ ){
	$base->compositeImage( $chars[$i], $chars[$i]->getImageCompose(), 1+($i*20), 1+($i*20)); //Compose frame and character
	echo "\nloop\n";
}

$base->writeImage('final.jpg');
