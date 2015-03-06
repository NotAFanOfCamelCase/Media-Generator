<?php

/**
 * Edits & Renders Image Sequences
 *
 * @name		Image Sequence Editor
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Dependencies:
 *		Imagick PHP Module
 *
 * Defaults:
 *		Frame Rate: 24
 *		Export Directory: ../exported/video/
 *
 * Abbreviations:
 *		Oxy: Original 	(x,y) position of a Character type object
 *		Fxy: Future 	(x,y) 	''
 *
 * TODO:
 *		Video Type Character Support
 *		Clip Framerate Analyzation
 **/

$lib_dir = realpath(__DIR__ . "/..");

require_once realpath(__DIR__) . '/Errors.class.php';
require_once realpath(__DIR__) . '/Frame.class.php';
require_once realpath(__DIR__) . '/Disk.class.php';
require_once realpath(__DIR__ ). '/Abstract.class.php';
require_once realpath(__DIR__) . '/Character.class.php';
require_once "{$lib_dir}/3party/cli_color/Cli_Color.php";
//require_once 'Plugins.php';

 /**
  * Sequencer
  *
  * @class Sequencer: Handles frame by frame sequences
  * @extends Base
  * @abstract method curr_dir()
  **/
class Sequencer extends Base{

	# VIDEO & UTILITIES #
	private $video_type;
	private $template_file_count;
	private $template_path;
	private $frames;
	private $characters			= array();
	private $e_process			= array();
	private $type;
	private $base_color;
	# ERROS, FILES & LOGGING #
	private $errors, $disk, $color_string;
	private $temporary_folder	= 'tmp';
	private $root_directory		= __DIR__;
	private $_info				= array();
	private $allowed_types		= array('jpg', 'png', 'gif', 'bmp');
	private $python				= array();
	private $python_path;

	/**
	* Constructor: Sets the type of video to generate & template path to use
	*
	* @param string $session_id 	Path to template
	* @param string $template_path 	Image sequence extension
	* @param string $type			(Optional) Extension of frames. If not set the script will use the extension
	*								of the first file listed in the folder.
    **/
	function __construct( $temporary_folder='tmp', $template_path, $type=null )
	{
		$this->template_path			= $template_path;
		$this->python_path				= "{$this->root_directory}.../python/";
		$this->template_file_count		= shell_exec("ls -1 {$this->template_path} | wc -l"); //Determine number of frames
		$this->errors					= new Errors;
		$this->disk						= new Disk;
		$this->color_string				= new Cli_Color;
		$this->type						= $type;
		$this->temporary_folder			= $temporary_folder;
		//$this->python_load();			//Load Python libraries
		//$this->loadPlugins();			//Initialize plugins
		echo "\nReading template\n";
		try{
			$this->loadTemplate();
		}catch( Exception $e ){
			echo "\n{$this->errors->msg(210)}{$e->getMessage()}\n";
			return false;
		}
		echo "\nread\n";
	}
	
	
	/**
	* Python Load: Get all Python extensions and store paths in memory
	*
	* @return void
    **/
	private function python_load()
	{
		$this->python			= $this->disk->discover_dir($this->python_path, 'py');
		
		if( count($this->python) <1 ){
			return;
		}

		foreach( $this->python as &$file ){
			$file					= array(basename($file) => $file);
		}
		return;
	}
	
	
	/**
	* Python Get: Get path to requested extension
	*
	* @return mixed 'False' if extension is not recognized, 'string' of path to extension otherwise
    **/
	private function python_get($extension)
	{
		if( ! array_key_exists($extension, $this->python) ){
			echo "{$this->errors->msg(221)} {$extension}";
			return false;
		}else{
			return $this->python[$extension];
		}
	}


	/**
	* Load: Fetch all frames of template sequence and insert into a frame object inside an array
	*
	* @param string (Optional) Set the extension of the frame sequence
	* @thorws InvalidArgumentException If no frames are found in supplied directory 
	* @thorws InvalidArgumentException When a frame fails to load properly
	* @return bool Returns 'true' on success
    **/
	private function loadTemplate()
	{
		$dim_check	= true;
		
		if( $this->type == NULL ){	//Attempt to figure out the extension of the template files by looping
									//through all the files until we hit an extension in "$allowed_types[]", then use that extension	
			$allowed	= false;
			$iterate	= 0;

			do{
				$dir_tmp	= scandir($this->template_path);		//Scan template directory
				$this->type	= pathinfo( $dir_tmp[$iterate], PATHINFO_EXTENSION );	//Get extension at position "$iterate"

				if( in_array($this->type, $this->allowed_types) ){	//Check that it is a valid type
					$allowed	= true;	//Set the condition to true
				}

				$iterate++;	//Adavance iteration

			}while( ! $allowed || $iterate > count($dir_tmp) );

			//If we reached this part and "$allowed" is still false
			if( ! $allowed ){
				throw new Exception( "\n{$this->errors->msg(217)} {$this->template_path} - Invalid file type\n" );
				return false;
			}
		}else{
			$this->type = str_replace('.', '', $this->type);		//Take off 'dot' in case the parameter was set with one
		}

		//Scan template directory and get all files of the appropiate extension into an array
		$frame_files		= $this->disk->discover_dir($this->template_path, $this->type);
		$i					= 0;										//Counter for subsets

		//Check that we received at least 1 frame
		if( count( $frame_files ) < 1 ){
			throw new Exception( "\n{$this->errors->msg(203)}\n" );
			return false;
		}
		echo "\n";
		$total_files		= count($frame_files);

		//Loop through all files in array

		foreach( $frame_files as &$file )
		{
			echo "Reading file " . ($i + 1) . " of {$total_files}\r";
			$file					= realpath($file);
			
			$this->frames[] 		= new Frame;							//New frame object
			$file_name				= pathinfo( $file, PATHINFO_FILENAME );

			if( $dim_check ){
				//Get frame dimmensions
				$dim_arr				= getimagesize($file);
				$this->_info['dimmensions']['width']	= $dim_arr[0];
				$this->_info['dimmensions']['height']	= $dim_arr[1];
				
				$dim_check	= false;
			}

			$new_file_destination	= "{$this->temporary_folder}/{$file_name}.{$this->type}";

			/*Copy file to temp folder*/
			if( ! copy( $file, $new_file_destination ) ){
				throw new Exception( "\n{$this->errors->msg(212)}{$file}\n" );		//Thow exception if insertion fails
				return false;
			}else{
				$file				= $new_file_destination;
			}
			//Attempt to insert frame, check response
			if( ! $this->frames[$i]->insert( $file, $this->type ) ){
				throw new Exception( "\n{$this->errors->msg(202)} {$file}\n" );		//Thow exception if insertion fails
				return false;
			}
			$i++;
		}

		return true;
	}
	
	
	/**
	* Load: Fetch all frames of template sequence and insert into a frame object inside an array
	*
	* @return bool Returns 'true' on success, 'false' otherwise
    **/
	private function loadPlugins()
	{
		$plugins		= $this->disk->discover_dir("{$this->root_directory}/../plugins/", 'php'); //Get all plugins from folder
		$bool			= true;		//Return variable

		if( is_array($plugins) ){	//Check that we got an array
			foreach( $plugins as $plugin ){
				if( php_check_syntax( $plugin ) ){	//Validate syntax
					require_once $plugin;
				}else{
					echo "\n{$this->errors->msg(220)} {$plugin}\n";
					$bool		= false;
				}
			}
		}

		return $bool;
	}


	/**
	* Frames Validation: Checks that all frames in sequence exist
	*
	* @throws exception Throws failed frames
	**/
	private function framesValidation()
	{
		$invalid_frames		= '';
		$failture			= false;
		$f_counter			= 0;
		//Loop through all frames
		foreach( $this->frames as $frame ){
			//Check if the frame exists
			if( ! file_exists( $frame->get_frame() ) ){
				$invalid_frames		= "{$invalid_frames}\nFrame {$f_counter} @ {$frame->getFrame()}";
				$failture			= true;
			}

			$f_counter++;
		}
		
		if( $failture == true ){
			throw new Exception($invalid_frames); //Return list of bad frames
		}else{
			return true;
		}
	}


	/**
	*					DEPRECATED: Function translated to Python
	*
	* Tween: Animates objects on top of present background
	*
	* @param character $character Character object to be overlayed
	* @param int 	   $current_x The current X location of the subject
	* @param int 	   $current_y The current Y location ''
	* @param int 	   $future_x  The future X location of the subject
	* @param int 	   $future_y  The future Y location ''
	* @param int 	   $start     Tween starting point
	* @param int 	   $duration  Running time of the tween
	* @return bool 				  Returns 'true' on success
    
	public function tween( $character, $current_x, $current_y, $future_x, $future_y, $start, $duration )
	{
		if( ! $this->is_character( $character ) ){
			echo "\n{$this->errors->msg(216)}\n";
			return false;
		}
	
		$start				= $start-1;
		$duration			= $duration-1;
		$frame_op			= $start; //Frame to operate on
		$video_char			= false;
		Time validation
		if( ! $this->timeValidation( $start, $duration ) )
		{
			echo "\n{$this->errors->msg(201)}\n";
			return false;
		}
		//Check is character is a video
		if( $character->info('type') == 'mov' || $character->info('type') == 'mp4' ){
			$video_char			= true;
		}
		
		exec("{$this->root_directory}/.../python/Sequencer.py "); //Call python tween script
		
		if( $video_char ){//If character is video reset frame back to zero
			$character->v_frame('reset');
		}
	}
		*/
		
		
	/**
	* Tween: Animates objects on top of present background
	*
	* @param character $character Character object to be overlayed
	* @param int 	   $current_x The current X location of the subject
	* @param int 	   $current_y The current Y location ''
	* @param int 	   $future_x  The future X location of the subject
	* @param int 	   $future_y  The future Y location ''
	* @param int 	   $start     Tween starting point
	* @param int 	   $duration  Running time of the tween
	* @return bool 				  Returns 'true' on success
    **/
	public function tween( $character, $current_x, $current_y, $future_x, $future_y, $start, $end )
	{
		if( ! $this->is_character( $character ) ){
			echo "\n{$this->errors->msg(216)}\n";
			return false;
		}
		
		echo "\n";
		$start				= $start-1;
		$duration			= $end - $start;
		$frame_op			= $start; //Frame to operate on
		$video_char			= false;
		/*Time validation*/
		if( ! $this->timeValidation( $start, $end ) )
		{
			echo "\n{$this->errors->msg(201)}\n";
			return false;
		}
		//Check is character is a video
		if( $character->info('type') == 'mov' || $character->info('type') == 'mp4' ){
			$video_char			= true;
		}
		
		/*Initialize instances*/
		$IMGK_CHARACTER		= new Imagick( $character->get() );
		/*Do Some Meth & Math*/
		$IMGK_VALUES		= self::CALCULATE_TWEEN_VALS( $current_x, $current_y, $future_x, $future_y, $duration );
		
		if( ! $IMGK_VALUES )
			{return false;}
		echo $this->color_string->getColoredString("\nTweening character '{$character->name()}':\n", 'yellow');
		//Frame by frame processing begins
		foreach( $IMGK_VALUES as $value ){
			echo "Working @ frame {$frame_op}\r";
			$this->overlay( &$character, $value['x'], $value['y'], $frame_op );
			$frame_op++;
		}
		
		if( $video_char ){//If character is video reset frame back to zero
			$character->v_frame('reset');
		}
	}


	/**
	*					DEPRECATED: Function translated to Python
	*
	* Tween: Animates objects on top of present background
	*
	* @param string $text 	Text to generate
	* @param string $color 	Font color
	* @param string $size 	Font size
	* @param string $font  	Font type
	* @return mixed 		Returns character with text on success, 'false' otherwise
    **/
	public function text($text, $color='black', $size='30', $font='Helvetica')
	{
		/* Create some objects */
		$image 			= new Imagick();
		$draw 			= new ImagickDraw();
		$pixel 			= new ImagickPixel('transparent'); //Set global alpha

		/* New image use stage dimmensions*/
		$image->newImage($this->_info['dimmensions']['width'], $this->_info['dimmensions']['height'], $pixel);

		/* Black text */
		$draw->setFillColor($color);

		/* Font properties */
		$draw->setFont($font);
		$draw->setFontSize($size);

		/* Create text */
		$image->annotateImage($draw, 50, 50, 0, $text);

		/* Give image a format */
		$image->setImageFormat('png'); //PNG always, for transparency

		//Generate new random file name
		$file_name			= $this->disk->new_file($this->temporary_folder, '.png');

		$image->writeImage($file_name); //Export Image

		//Generate random name
		$name			= time();
		$name			= "{$name}{$this->rand_string()}";
		$name			= md5($name);

		if( ! $this->new_character($file_name, $name, $this->temporary_folder) ){
		//Create new character
			echo "\n{$this->errors->msg(218)}\n";
			return false;
		}
		
		return $this->characters[$name];
	}


	/**
	*					DEPRECATED: Function translated to Python
	*
	* Overlay: Overlays a character onto the background
	*
	* @param character $character Character object 
	* @param int 	   $x 		  The current Y location ''
	* @param int 	   $y 		  The future X location of the subject
	* @return bool 				  Returns 'true' on success
    **/
	public function overlay( $character, $x, $y, $start, $end='' )
	{
		if( $end == '' ){
			$end = $start;
		}
		/*Time validation*/
		if( ! $this->timeValidation( $start, $end ) )
		{
			echo "\n{$this->errors->msg(201)}\n";
			return false;
		}
		/* New 'Imagick' object from 'Character' object*/
		$IMGK_CHARACTER		= new Imagick( $character->get() );

		/*Build sequence*/
		for( $counter = $start ; $counter < ( $end + 1 ) ; $counter++ )
		{
			echo "Working frame {$counter} of {$end}\r";
			$this->frames[$counter]->composer( $IMGK_CHARACTER, $x, $y ); //Compose frame and character
		}
	}
	
	
	/**
	* Fade: Overlays a character onto the background
	*
	* @param character $character Character object 
	* @param int 	   $x 		  The current Y location ''
	* @param int 	   $y 		  The future X location of the subject
	* @return bool 				  Returns 'true' on success
    **/
	public function fade( $start, $end )
	{
		$fader	= new Imagick( $character->get() );
	}


	/**
	* Time Validation: Validate that the running time of the effect does not exceed the total length of the movie
	*
	* @param int $start Starting frame of action
	* @param int $end   Ending frame of action
	* @return bool Returns 'true' when action running time is valid, false otherwise
    **/
	private function timeValidation( $start, $end )
	{
		if( $start > $end ){
			echo "\n{$this->errors->msg(207)}\n";
			return false;
		}elseif( $start > ($this->template_file_count) ){
			echo "\n{$this->errors->msg(206)}\n";
			return false;
		}elseif( $end > ($this->template_file_count) ){
			echo "\n{$this->errors->msg(208)}\n";
			return false;
		}else
			{return true;}
	}


	/**
	* Dump Frames: Show all frames in current sequence
	* @return void Nothing to see here, move along
    **/
	public function dump_frames()
	{
		foreach( $this->frames as $frame ){
			echo "\n{$frame->get_frame()}\n";	//Shows location of frame
		}
	}


	public function center_2_point($character, $x, $y)
	{
		$path	= $character->get();
		list($width, $height)	= getimagesize($path);

		return array($x - ($width/2), $y - ($height/2));
	}


	/**
	* Current Directory: Directory of currrent class
	* @return string Directory 
    **/
	public function curr_dir()
	{
		return $this->root_directory;
	}


	/**
	* New Character: Create new character object
	*
	* @param string $location Location to 'character'
	* @param string $name	  Name for 'character'
	* @return bool 'True' on success, 'false' otherwise
	**/
	public function new_character( $location, $name, $temp_folder, $accept='all' )
	{
		try{
			$temp	= new Character( $location, $name, $temp_folder, $accept );	//Attempt to load new media
		}catch( Exception $e ){
			echo "\n{$e->getMessage()}\n";
			echo "\n{$this->errors->msg(209)}\n";	//Catch error Exception
			return false;
		}

		$this->characters[$name]		= $temp;	//Add to characters array
		return true;
	}


	/**
	*					DEPRECATED: Function translated to Python
	*
	* Calculate Values: Takes input 'Oxy' & 'Fxy' and calculates speed 
	*					in PPF (Pixels Per Frame)
	*
	* @param int $current_x The current X location of the subject
	* @param int $current_y The current Y location ''
	* @param int $future_x 	The future X location of the subject
	* @param int $future_y 	The future Y location ''
	* @param int $duration 	Duration of transition (Frames)
	* @return array Array with values per frame - ( arr[<FRAME>][x], arr[<FRAME>][y] )
	**/
	private function calculate_tween_vals( $current_x, $current_y, $future_x, $future_y, $duration )
	{
		if( $duration == 0 ){
			echo "\n{$this->errors->msg(214)}\n";
			return false;
		}

		$x_speed			= ( $future_x - $current_x ) / ($duration-1);	//Figure X Speed
		$y_speed			= ( $future_y - $current_y ) / ($duration-1);	//Figure Y Speed
		$values				= array(); //Initialize values array
		$x_new_position		= $current_x; //Placeholder for position per frame
		$y_new_position		= $current_y; //          	 ''            
		$values[0]['x']		= $current_x; //Store current X position
		$values[0]['y']		= $current_y; //Store current Y position

		for ( $i = 1 ; $i <= ($duration-1) ; $i++ ){
			$values[$i]['x']	= ($x_new_position = $x_new_position + $x_speed); //Store 'X' value at frame '$i'
			$values[$i]['y']	= ($y_new_position = $y_new_position + $y_speed); //Store 'Y' value at frame '$i'
		}

		$values[($duration)]['x']	= $future_x; //Store final X position
		$values[($duration)]['y']	= $future_y; //Store final Y position
		
		return $values;
	}
	
	
	/**
	* Frame Replication: Replicates single frame to a range of frames
	*
	* @param int $frame 	Subject Frame
	* @param int $s_range 	Start of Range
	* @param int $e_range 	(Optional) End of range
	* @return bool 'True' on success, 'false' otherwise
	**/
	public function replicate( $frame, $s_range, $e_range=NULL )
	{
		if( $e_range == NULL ){
			$e_range = ( $s_range + 1 );
		}

		//What!?
		$failture 			= false;
		$failed_frames		= "";

		//What!?
		for( $i = $s_range ; $i < ( $e_range ) ; $i++ ){
			if( ! $this->frames[$i]->insert($this->frames[$frame]->get_frame(), $this->type ) ) {
				$failed_frames	= "{$failed_frames}\n{$i}";
				$failture = true;
			}
		}

		//What!?
		if( $failture ){
			echo "\n{$this->errors->msg(215)}{$failed_frames}\n";
			return false;
		}

		return true;
	}


	/**
	* Export: Exports all frames and exports them into an image sequence
	*
	* @return mixed Location of exported sequence, 'false' on failture
	**/
	public function export()
	{
		$position_counter		= 1;
		$render_loc				= $this->disk->new_file($this->temporary_folder, '');
		$frame_count			= sizeof($this->frames);
		
		//Validate that all frames exist
		try{
			$this->framesValidation( $this->frames );
		}catch( Exception $e ){
			echo "\n{$this->errors->msg(205)}{$e->getMessage()}\n";
		}
		
		//Create folder for export
		if( ! mkdir($render_loc) ){
			echo "\n{$this->errors->msg(219)} @ {$this->temporary_folder}\n";
			return false;
		}
		echo "\n";
		//Loop through all frames:
		foreach( $this->frames as $frame ){
			echo "Exporting frame {$position_counter} of {$frame_count}\r";
			$file_name			= $this->disk->new_file("{$this->temporary_folder}", $this->type);
			$frame->render($file_name); //Render any tasks in image composing queue

			$frame_name			= $this->frame_file($position_counter);
			if( ! copy( $frame->get_frame(), "{$render_loc}/~{$frame_name}.{$this->type}" ) ){	//Copy final frame to export directory
				echo "\n{$this->errros->msg(212)}\n";
				return false;
			}
			$position_counter++;
		}

		return $render_loc;
	}


	/**
	* Frame File: Returns the appropiate name for the file with the correct amount of zero prefixed
	*
	* @param int $position Frame position
	* @return int File digit
	**/
	private function frame_file( $position )
	{
		$digit_count	= strlen($position);

		if( $digit_count >= ( strlen( $this->total_frames() ) + 1 ) ){
			return $position;
		}else{
			$position		= "0{$position}";
			return $this->frame_file($position);
		}
	}


	/**
	* Total Frames: Return the total amount of frames in sequence
	*
	* @return int Number of frames
	**/
	public function total_frames()
	{
		return count($this->frames);
	}


	/**
	* Get Character: Gets location of character
	*
	* @param string $identifier Name of character
	* @return mixed 'False' if character does not exist, 'String' of character location otherwise
	**/
	public function fetch_character($identifier)
	{
		if( ! array_key_exists( $identifier, $this->characters ) ){
			echo "\n{$this->errors->msg(211)}{$identifier}\n";
			return false;
		}

		return $this->characters[$identifier];
	}


	/**
	* Is Character: Checks that value provided is a Character type object
	*
	* @param mixed $var $identifier Name of character
	* @return bool 'True' if parameter is a Character type object, 'false' otherwise
	**/
	public function is_character( $var )
	{
		if( method_exists( $var, 'checksum' ) && $var->checksum() == md5('Character') ){
			return true;
		}else{
			return false;
		}
	}


	/**
	* Information: Gets data from information array
	*
	* @param string $info Data request
	* @return mixed Data requested, 'false' otherwise
	**/
	public function info($info='about')
	{
		if( ! array_key_exists($info, $this->_info) ){
			echo "\n{$this->errors->msg()}\n";
			return false;
		}else{
			return $this->_info;
		}
	}


	/**
	* Random String: Generates a random a-z/A-Z/0-9 string base on the length
	*
	* @param int $length 
	* @return string Generated string
	**/
	private function rand_string( $length=5 )
	{
		$chars 	= "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";	//Allowed characters
		$str  	= "";

		$size = strlen( $chars );

		for( $i = 0; $i < $length; $i++ ) {
			$str .= $chars[ rand( 0, $size - 1 ) ];
		}

		return $str; //Return string
	}


	/**
	* Find Pixel: Gets the X & Y of the first occurrence of a specific pixel
	*
	* @param string $img 	Path to image
	* @param int $r		 	Red level
	* @param int $g		 	Green level
	* @param int $b		 	Blue level
	* @param int $tolerance	Tolerance level, 5 by default
	* @return mixed An array with the X & Y values if found, 'false' otherwise
	**/
	public function findPixel($img, $r, $g, $b, $tolerance=5)
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


	/**
	* Track Pixel: Tracks a pixel of a specific color through the sequence
	*
	* @param string $img 	Path to image
	* @param int $r		 	Red level
	* @param int $g		 	Green level
	* @param int $b		 	Blue level
	* @param int $tolerance	Tolerance level, 5 by default
	* @return mixed An array with the X & Y values if found, 'false' otherwise
	**/
	public function track($files, $r, $g, $b, $tolerance=5)
	{
		$black 		= array();

		foreach($files as $file)
		{
			$black[]	= findPixel($file, 0, 0, 0, $tolerance);
		}

		return $black;
	}

}