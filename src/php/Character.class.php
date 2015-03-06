<?php

/**
 * Character: Allows the manipulation of a audio visual elements from a simple API
 *
 * @name		Character Class
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos@gmail.com>
 *
 * Dependencies:
 * 		For video processing: FFMPEG & Sox are required
 *		Imagick PHP Plugin for image manipulation
 **/

require_once realpath(__DIR__) . '/Disk.class.php';
require_once realpath(__DIR__) . '/Errors.class.php';
require_once realpath(__DIR__) . '/Frame.class.php';

class Character {

	private $location, $temp_folder;
	private $name;
	private $type;
	private $disk, $errors;
	private $v_character_types	= array(
										'jpg',
										'jpeg',
										'gif',
		/*Allowed visual types*/		'png',
										'bmp',
										'mov',
										'mp4'
										);
	private $a_character_types	= array(
										'mp3',
		/*Allowed audio types*/			'ogg',
										'wav'
										);
	private $allowed_video		= array(
										'mov',
										'mp4'
										);
	private $checksum;
	private $tolerance			= array('visual', 'audio', 'all');
	private $frames				= array();
	private $audio				= NULL;
	private $video				= false;
	private $accept, $frame_rate;
	private $parent_dir;
	private $info				= array();


	/**
	* Constructor: Builds Object
	*
	* @param string $location Location to media
	* @param string $name Name for character
	* @throws exception If character is rejected
    **/
	function __construct( $location, $name, $temp_folder='/tmp', $accept='all' )
	{
		$this->disk			= new Disk;
		$this->errors		= new Errors;
		$this->frame_rate	= NULL; //Used for video characters, frame rate will be determined upon initialization
		$accept				= strtolower($accept);
		$relative			= realpath(__DIR__);
		$this->parent_dir	= $relative;

		if( ! is_dir( "{$temp_folder}" ) ){
			echo "\n{$this->errors->msg(600)}:{$temp_folder}\n";
			return false;
		}

		if( ! in_array( $accept, $this->tolerance ) ){	//Check tolerance level
			echo "\n{$this->errors->msg(700)} - '{$accept}' is invalid\n";
			return false;
		}else{
			$this->accept	= $accept;
		}

		try{							//Validate media file
			$this->disk->validate_file($location);
		}catch( Exception $e ){
			throw new Exception($e->getMessage());		//Throw exception if validation fails
			return false;
		}

		//If we got this far it's now safe to set these values
		$this->location		= $location;
		$this->name			= $name;
		$this->temp_folder	= $temp_folder;

		//Check character type
		if( ! $this->type_validate() ){
			echo "{$this->errors->msg(700)}";
			return false;
		}

		//Determine if this is a .mov or .mp4
		if( $this->type == 'mov' || $this->type == 'mp4' ){
			try{
				$this->import_video($location); //Import video
			}catch( Exception $e ){
				echo "\n{$e->getMessage()}\n";
			}
		}

		$this->checksum		= md5(__CLASS__);
	}


	/**
	* Location: Return location of character on disk
	*
	* @return string Location of character
    **/
	public function get()
	{
		return $this->location;
	}


	/**
	* Type: Returns the extension of the character media
	*
	* @return string Media file extension
    **/
	public function type()
	{
		return $this->type;
	}


	/**
	* Dimensions: Returns height & width of character
	*
	* @return array Width & Height respectively
    **/
	public function dimensions()
	{
		if( $this->tolerance == 'audio' || in_array( $this->type, $this->a_character_types ) ){
			echo "\n{$this->errors->msg(303)}\n";
			return false;
		}

		list($width, $height) = getimagesize($this->location); 
		$dim	= array('width'		=> $width,
						'height'	=> $height);

		return $dim;
	}


	/**
	* Type Validation: Check that the characted that is being inserted is allowed
	*
	* @param string $location Path to file that is being inserted
	* @return bool 'True' on success, 'false' otherwise
    **/
	private function type_validate()
	{
		$ext_temp	= pathinfo($this->location, PATHINFO_EXTENSION);

		//Check if extension is acceptable and check type tolerance
		if( ( 	in_array( 		$ext_temp, 	$this->a_character_types )	//Check for 'all'
				|| in_array( 	$ext_temp, 	$this->v_character_types ) ) 
				&& 	$this->tolerance == 'all' )
		{
			$this->type				= $ext_temp;
			$this->info['type']		= $ext_temp;
			return true;

		}elseif( in_array( $ext_temp, $this->a_character_types) && $this->tolerance = 'audio' ){ //Check 'audio' tolerance levek
			$this->type 			= $ext_temp;
			$this->info['type']		= $ext_temp;
			return true;
		}elseif( in_array( $ext_temp, $this->v_character_types) && $this->tolerance = 'visual' ){ //Check 'visual' tolerance level
			$this->type 			= $ext_temp;
			$this->info['type']		= $ext_temp;
			return true;
		}else{
			return false;
		}
	}


	/**
	* Import Video: Imports video to character files
	*
	* @return bool 'True' on success, 'false' otherwise
	* @throws Exception When frame fails to load
    **/
	private function import_video()
	{
		//Create temporary folder for storing frames
		$new_temp			= $this->disk->new_file($this->temp_folder);
		$itinerator			= 0;
		$this->frame_rate	= $this->get_fps($this->location);

		//Check that we were able to get the video's frame rate
		if( ! $this->frame_rate ){
			echo "\n{$this->errors->msg(703)}{$this->location}\n";
			return false;
		}

		if( ! mkdir("{$new_temp}", 0777) ){
			echo "\n{$this->errors->msg(702)}\n";
			return false;
		}

		$run_command		= "ffmpeg -r {$this->frame_rate} -i {$this->location} -an -crf 30 -r {$this->frame_rate} {$new_temp}/output_%05d.png";
		$run_command		= str_replace("\n", "", $run_command);

		//Convert video to sequence
		shell_exec($run_command);

		//Get all 'png' files into array
		$dir_tmp	= $this->disk->discover_dir($new_temp, 'png');

		//Load frames from array
		foreach( $dir_tmp as $file ){
			$this->frames[$itinerator]			= new Frame;
		
			if( ! $this->frames[$itinerator]->insert( $file, 'png' ) ){
				throw new Exception( "\n{$this->errors->msg(202)} {$file}\n" );		//Thow exception if insertion fails
				return false;
			}

			$itinerator++;
		}

		//Extract Sound
		shell_exec(" ffmpeg -i {$this->location} -vn -ac 2 -ar 44100 -ab 320k -f wav {$new_temp}/output.wav ");
		
		//Check that we have extracted sound, if not, the video did not contain any sound
		//in wich case $this->audio will stay as NULL
		if( file_exists( "{$new_temp}/output.wav" ) ){
			$this->audio			= "{$new_temp}/output.wav";
			$this->info['audio']	= "{$new_temp}/output.wav";
		}
		
		$this->video			= $this->location;	//Store original location of the video
		$this->info['video']	= $this->location;
		$this->location			= $this->frames[0];		//Store location to firat image in the sequence
		$this->info['location']	= $new_temp;
		$this->info['duration']	= count($this->frames);
		$this->info['iterate']	= 0;

		return true;
	}
	
	
	/**
	* V_Frame: Allows frame itineration in 'video' type characters
	*
	* @param string $option The command to execute in the function
	* @return bool 'True' on success, 'false' otherwise
    **/
	public function v_frame($option='reset')
	{
		if( $this->info['type'] == 'mp4' || $this->info['type'] == 'mov' ){
			if( $option == 'next' ){
				if( $this->info('iterate') == ( count($this->frames) - 1 ) ){
					$this->info['iterate'] = 0; //Reset if we're trying to go beyond the total frames
					$this->location			 = $this->frames[0];
					return true;
				}else{
					$this->info['iterate']++;
					$this->location		= $this->frames[$this->info['iterate']];
					return true;
				}
			}elseif ( $option == 'prev' ){
				if( $this->info('iterate') == 0 ){
					$this->info['iterate'] = 0; //Reset if we're trying to go beyond the total frames
					$this->location			 = $this->frames[count($this->frames)-1];	//Go to last frame when attempting to back up out of bounce
					return true;
				}else{
					$this->info['iterate']--;
					$this->location		= $this->frames[$this->info['iterate']];
					return true;
				}
			}elseif( $option == 'reset' ){
					$this->info['iterate'] = 0;
					$this->location			 = $this->frame[0];
					return true;
			}else{
				echo "\n{$this->errors->msg(705)}\n";
				return false;
			}
		}else{
			echo "\n{$this->errors->msg(706)}\n";
			return false;
		}
	}


	/**
	* Get FPS: Gets video's frame rate
	*
	* @param string $file Path to video file to analyze
	* @return mixed Frame rate of video on success, 'false' otherwise
    **/
	private function get_fps( $file )
	{
		if( file_exists($file) ){		
			return shell_exec('ffmpeg -i ' . $file . ' 2>&1 | sed -n "s/.*, \(.*\) fp.*/\1/p"');
		}else{
			return false;	//We didn't find the frame rate, return false
		}
	}
	
	
	/**
	* Constraint: Sets a max size for image, rezises if necessary
	*
	* @param int $height Max image height
	* @param int $width  Max image width
	* @return bool 'True' on success, 'false' otherwise
    **/
	public function constrain($height, $width)
	{
		//Check if character is an image
		if( in_array($this->type(), $this->a_character_types) || in_array($this->type(), $this->allowed_video) )
		{
			echo "\n{$this->errors->msg(704)} This is a '.{$this->type()}' file\n";
			return false;
		}
		$dimensions		= $this->dimensions();
		$imagick		= new Imagick($this->get());
		echo "\n{$this->get()}\n";

		//Check height
		if( $dimensions['height'] > $height )
		{
			$new_file				= $this->disk->new_file($this->temp_folder, $this->type());

			$scale_by				= $dimensions['height'] - $height;

			$new_height				= $dimensions['height'] - $scale_by;
			$new_width				= $dimensions['width'] -  $scale_by;

			//Update dimmensions
			$dimensions['height']	= $new_height;
			$dimensions['width']	= $new_width;
			
			$imagick->scaleImage($new_width, $new_height);
			$imagick->writeImage($new_file);
			$this->location			= $new_file;
		}
		//Check width
		if( $dimensions['width'] > $width){
			$new_file				= $this->disk->new_file($this->temp_folder, $this->type());

			$scale_by				= $dimensions['width'] - $height;
			
			$new_height				= $dimensions['height'] - $scale_by;
			$new_width				= $dimensions['width'] -  $scale_by;
			
			//Update dimmensions
			$dimensions['height']	= $new_height;
			$dimensions['width']	= $new_width;
		
			$imagick->scaleImage($new_width, $new_height);
			$imagick->writeImage($new_file);
			$this->location			= $new_file;
		}
	}


	public function checksum()
	{
		return $this->checksum;
	}


	public function name()
	{
		return $this->name;
	}
	
	public function frate()
	{
		return $this->frame_rate;
	}

	
	public function as_video()
	{
		return $this->video;
	}
	
	
	public function info($request)
	{
		if( array_key_exists( $request, $this->info ) ){
			return $this->info[$request];
		}else{
			return false;
		}
	}
}