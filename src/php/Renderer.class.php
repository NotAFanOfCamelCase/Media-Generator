<?php

/**
 * Rendering library using FFMPEG
 *
 * @name		Media Renderer
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Dependencies:
 * FFMPEG
 * SOX
 **/

require_once __DIR__ . '/Errors.class.php';
require_once __DIR__ . '/Abstract.class.php';
require_once __DIR__ . '/Disk.class.php';

class Renderer extends Base{

	private $ffmpeg_path;
	private $export_path;
	private $errors;
	private $file_name;
	private $prefix;
	private $disk;
	private $root_directory		= __DIR__;
	private $frame_rate;
	private $path_rendered_location	= false;


	/**
	* Constructor: Sets the type of video to generate & template path to use
	* @param array Array with frames type of object
	* @param string Path to export the video to
	* @param string (Optional) Path to FFMPEG, if not set the script will attempt to 
	*						   find it. (!)It is RECOMMENDED that the path is supplied
	* @param string (Optional) Output file save name, 'output' by default
	* @param string (Optional) Prefix of sequence, SHOULD ALWAYS BE '~'
    **/
	function __construct( $export_path, $frame_rate=24, $file_name='output', $ffmpeg_path='', $sox_path='' )
	{
		$this->export_path		= $export_path;
		$this->errors			= new Errors;
		if( ! file_exists($this->export_path) ){		//Check that export path exists
			echo "\n{$this->errors->msg(502)}\n";
		}
		$this->frame_rate		= $frame_rate;
		$this->file_name		= $file_name;
		$this->disk				= new Disk;
		/*FFMPEG Check*/
		if( $ffmpeg_path == '' ){
			if( ! self::IS_INSTALLED('ffmpeg') ){	//If the path to FFMPEG was not set attempt to find it
				echo "\n{$this->errors->msg(500)}\n";
			}
		}else{
			$this->ffmpeg_path		= $ffmpeg_path;
		}
	}


	/**
	* Sequence Rendering: Fetched 
	* @param string Path to image sequence
	* @return bool 'True' if video rendered, 'false' otherwise
	**/
	public function join_sequence($path_to, $extension='png', $prefix='~')
	{
		$this->prefix					= $prefix;	//Set sequence prefix
		$this->sequence_extension		= $extension;
		$new_file						= $this->disk->new_file($this->export_path, '.mp4');
		$sequence_files					= $this->disk->discover_dir($path_to, $extension);
		$single_file					= pathinfo($sequence_files[0], PATHINFO_FILENAME); //Get first file of sequence and
		$zero_count						= self::ZERO_COUNT($single_file);		// determine how many zeros there are
		$zero_count++;	//Increase for total amount of digits
		/*Execute FFMPEG Command*/
		shell_exec(" ffmpeg -r {$this->frame_rate} -i {$path_to}/{$this->prefix}%0{$zero_count}d.{$this->sequence_extension} -vcodec libx264 -crf 30 -r {$this->frame_rate} {$new_file} ");

		return $new_file;	//Validate render
	}


	/**
	* Validate Render: Checks that the file was rendered successfully
	* @return bool 'True' on success, 'false' otherwise
    **/
	private function validate_render()
	{
		if( file_exists("{$this->export_path}/{$this->file_name}.mp4") ){			//Check that the files exists
			$filesize		= filesize("{$this->export_path}/{$this->file_name}.mp4");
			if($filesize > 1){		//Check the size of the file, anything below 1 is bad
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
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
	* Determine Zero Place: Counts the amount of zeros prefixed in the image sequence
	* @param string Path to file of first image in the sequence
	* @param string File name prefix
	* @return int Count of zeros
    **/
	public function zero_count($file)
	{
		$string 		= basename($file);
		$string 		= str_replace( $this->prefix, '', $string);
		$string_arr		= str_split($string);
		$counter		= 0;

		foreach( $string_arr as $substr ){
			if( $substr == '0' ){
				$counter++;
			}else{
				break;
			}
		}

		return $counter;
	}


	/**
	* Is Installed: Attempts to find the path to the APP provided
	*
	* @param string $app Application to search for
	* @return bool 'True' if found, 'false' otherwise
    **/
	private function is_installed($app)
	{
		$command				= shell_exec("whereis {$app}");
		$location				= explode(':', $command);
		if ( count($location) > 0 ){
			if( strpos( $location[1], 'ffmpeg' ) ){
				$this->ffmpeg_path		= str_replace("\n", '', $location[1]);
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}


	/**
	* Audio/Video: Renders audio & video into a single video
	* @param string Path to video
	* @param string Path to audio
	* @return bool 'True' if found, 'false' otherwise
    **/
	public function audio_video($video, $audio, $name='output')
	{
		try{	//Check video file
			$this->disk->validate_file($video);
		}catch( Exception $e ){
			echo "\n{$this->errors->msg(503)}{$e->getMessage()}\n";
			return false;
		}

		try{	//Check audio file
			$this->disk->validate_file($audio);
		}catch( Exception $e ){
			echo "\n{$this->errors->msg(503)}{$e->getMessage()}\n";
			return false;
		}
		
		shell_exec(" ffmpeg -i {$audio} -i {$video} -acodec copy -vcodec copy -target ntsc-dvd -aspect 16:9 -b:v 4000k -bufsize 4000k {$this->export_path}/{$name}.mp4 ");
		
		return $this->finalize(); //See method

	}


	/**
	* Rendered Location: Gets the location of 
	*
	* @return mixed 'String'- location of rendered video, 'false' is no video has been redered
    **/
	public function rendered_location()
	{
		if( ! $this->path_rendered_location ){
			echo "\n{$this->errors->msg(504)}\n";
			return false;
		}else{
			return $this->path_rendered_location;
		}
	}


	/**
	* Finalize: Checks that video has been rendered
	*
	* @return bool 'True' if render succeeds, 'false' otherwise
    **/
	private function finalize()
	{
		if( $this->validate_render() ){
			$this->path_rendered_location	= "{$this->export_path}/{$this->file_name}.mp4"; //Store file location
			return "{$this->export_path}/{$this->file_name}.mp4";
		}else{
			echo "\n{$this->errors->msg(501)}\n";
			return false;
		}
	}


	public function render_speech( $characters )
	{
		//Validate that all characters exist
		foreach( $characters as $character ){
			echo $character->get();
		}
	}

}