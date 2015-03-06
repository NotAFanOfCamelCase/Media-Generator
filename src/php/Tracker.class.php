<?php

/**
 * Tracks motion of pixels of a specific RGB value in image as sequence
 *
 * @name		Color Tracker
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Dependencies:
 *		Imagick PHP Module
 **/

require_once __DIR__ . '/TrackingData.class.php';
require_once __DIR__ . '/Errors.class.php';

class Tracker{

	private $errors;


	/**
	* Constructor: Initializes necessary object structures
	*
    **/
	function __construct()
	{
		$this->errors			= new Errors;
	}


	/**
	* Find Pixel: Gets the first occurrence of a specific color
	*
	* @param string $img 	 	Path to image to search
	* @param array  $needles 	RGB Colors to look for. eg. array('0,0,0', '255, 255, 255') : Will find black and white
	* @param int	$tolerance  Level of tolerance for color match.
	* @return array X, Y values of needle(s)
    **/
	public function findPixel($img, $needles, $tolerance=5, $step_size=14)
	{

		$original_ 				= new Imagick($img);
		$total_needles			= count($needles);
		$found_needles			= 0;
		list($width, $height)	= getimagesize($img);
		$return_arr				= array();


		for( $x = 0 ; $x < $width ; $x = $x + $step_size )
		{
			for( $y = 0 ; $y < $height ; $y = $y + $step_size )
			{
				$curr_color				= $original_->getImagePixelColor($x, $y)->getColorAsString();
				$colors 				= preg_replace('/[^-,0-9+$]/', '', $curr_color); 
				$colors					= explode(',', $colors);
				
				foreach( $needles as &$needle ) //Use reference in foreach
				{
					if($needle)
					{
						$needle_vals		= explode(',', $needle);

						if( 	( $needle_vals[0] <= ($colors[0]+$tolerance) && $needle_vals[0] >= ($colors[0] - $tolerance) ) 
							&&  ( $needle_vals[1] <= ($colors[1]+$tolerance) && $needle_vals[1] >= ($colors[1] - $tolerance) ) 
							&&  ( $needle_vals[2] <= ($colors[2]+$tolerance) && $needle_vals[2] >= ($colors[2] - $tolerance) )	)
						{
							
							//We've found a color we're looking for, let's get the top left corner
							$temp_x		= ($x - 1);
							$temp_y		= ($y - 1);
							$temp_loc	= array( ($x - 1), ($y - 1) );
							$flip		= 0; //Set target to 'X' : 0 = X, 1 = Y

							foreach( $temp_loc as $location )
							{
								do
								{
									$inner_track			= $original_->getImagePixelColor($temp_x, $temp_y)->getColorAsString();
									$track_ 				= preg_replace('/[^-,0-9+$]/', '', $inner_track); 
									$track_					= explode(',', $track_);
									$temp_x					= $temp_x - 1;				//Move back left

									if( ! $flip ){ 		//Check what direction we're targeting
										$location--; 	//Move back inside the color box
										$temp_x					= $location;
									}else{
										$location--;
										$temp_y					= $location;
									}

								}while( ( ( $track_[0] <= ( $colors[0] + $tolerance ) && $track_[0] >= ( $colors[0] - $tolerance ) )
									&&    ( $track_[1] <= ( $colors[1] + $tolerance ) && $track_[1] >= ( $colors[1] - $tolerance ) )
									&&    ( $track_[2] <= ( $colors[2] + $tolerance ) && $track_[2] >= ( $colors[2] - $tolerance ) ) )
									&& !  ($temp_x < 0) ); //Make sure we stop checking after we reached the end of the image

								$flip		= 1; //Switch target to 'Y'
							}


							$return_arr[$needle]	= array( $temp_x, $temp_y );
							$needle					= false;
							$found_needles++;

							if( $found_needles == $total_needles ){	//If we found all the colors we needed stop searching and return the array
								return $return_arr;
							}
							break;	//Break out
						}
					}
				}
			}
		}
		
		foreach( $needles as $key => $value )	//Loop through all needles
		{
			if( $value != false ){
				if( ! array_key_exists( $value, $return_arr ) ){	//Check which colors we didn't find and set as false
					$return_arr[$value]		= false;
				}
			}
		}

		return $return_arr;
	}


	/**
	* Track: Records the location of specific colors in an image sequence
	*
	* @param array  $files 	 	Path to images to track
	* @param array  $needles 	RGB Colors to look for. eg. array('0,0,0', '255, 255, 255') : Will find black and white
	* @param int	$tolerance  Level of tolerance for color match.
	* @return array Configuration object array with data of each needle
    **/
	public function track($files, $needles, $tolerance=5, $step_size=14)
	{
		$arr 			= array();
		$needle_f		= array();
		$total			= count($files);
		$time			= time();
		$curr			= 0;
		$starting		= null;
		$return_arr		= array();
		$coordinates	= array();
		echo "\n\n";

		//Setup tracking starting point arrays
		foreach( $needles as $needle ){
			$needle_f[$needle]			= false;	//Frame tracker, will hold frame where the dot is first seen
			$needle_counter[$needle]	= 1;	//
		}

		//Begin image processing
		foreach($files as $file)
		{
			$curr++;
			$elapsed	= time() - $time;
			echo "Tracking frame {$curr} of {$total} - Elapsed time: {$elapsed}\r";
			$arr[$curr]	= $this->findPixel($file, $needles, $tolerance);
		}
		echo "\n\n";
		$speed			= time() - $time;

		//Analyze results
		foreach( $arr as $frame )
		{
			foreach( $needles as $needle )
			{
				$coordinates[$needle][]	= $frame[$needle];
				
				if( is_array($frame[$needle]) && ( $needle_f[$needle] == false ) ){
					$needle_f[$needle]	= $needle_counter[$needle];
					echo "\nDetermined starting point to be {$needle_counter[$needle]}\n";
				}
				$needle_counter[$needle]++; //Advance frame tracker
			}
		}

		//Setup objects
		foreach( $needles as $needle ){
			$return_arr[$needle]	= new Tracking_Data(null, $coordinates[$needle], $needle_f[$needle]);
		}

		echo "Speed: {$speed}fps\n";
		return $return_arr;
	}


	/**
	* Export Configuration: Writes out configuration file of tracked objects
	*
	* @param configuration $cconfiguration Tracking data object
    **/
	private function export_config($configuration)
	{
		
	}


	/**
	* Read Configuration: Parses configuration file and creates a tracking configuration object
	*
	* @param configuration $cconfiguration Tracking data object
    **/
	private function read_config($file)
	{
		$file_handle = fopen($file, "r");
		while (!feof($file_handle)) {
		   $line = fgets($file_handle);
		   if( ! substr($line1, 1) == '#' )	//Ignore lines that begin with '#'
		   {
				
		   }
		}
		fclose($file_handle);
	}
}