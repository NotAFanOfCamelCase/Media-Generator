<?php

/**	
 * Sound Lib: Manipulates sound files
 *	
 * @name		Sound Engine
 * @package    	Media Video Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Denpendencies:
 * 		Sox
 *
 * TODO:
 * 		Change methods to 'Private'
 *
 */
 
require_once __DIR__ . '/Errors.class.php';
require_once __DIR__ . '/Disk.class.php';
 
class Mixer{

	private $errors;
	private	$temp_folder;
	private $disk;

	function __construct( $temp_folder='tmp' )
	{
		//Setup members
		$this->errors		= new Errors;
		$this->disk			= new Disk;
		
		if( ! is_dir($temp_folder) ){
			echo "\n{$this->errors->msg(902)}\n";
			return false;
		}
		
		$this->temp_folder	= $temp_folder;
		
		//Check system requirements
		if( ! self::GET_SOX() ){
			return false; //Not sure about this
		}
	}


	/**
	* Get Sox: Attempts to check is installed
	*
	* @return bool 'True' if found, 'false' otherwise
    **/
	private function get_sox()
	{
		$cli_output			= explode(' ', shell_exec(" sox --version ")); //Run shell command, make array
		
		if( $cli_output == 'sox:' || $cli_output != '-bash:' ){ //We expect one or the other
			return true;
		}else{
			echo "\n{$this->errors->msg(900)}\n";	//Show error message
			return false;
		}
	}


	/**
	* Join Speech: Using sox it joins all file in character array into a single sound file
	*
	* @param array Path to video
	* @param string Rendered file name
	* @return mixed Returns rendered file path on success, 'false' otherwise
    **/
	private function join_speech($characters, $output='output.mp3')
	{
		//Check parameters:
		if( ! is_array($characters) ){	//We expect an array
			echo "{$this->errors->msg(1)} Sound_E::join_speech() Expects an array";
			return false;
		}elseif( ! (get_class($characters[0]) == 'Character') ){	//We expect to be a character type
			echo "{$this->errors->msg(1)} Sound_E::join_speech() Expects character type objects in array";
			return false;
		}

		//Validate file name & type
		$new_file	= $this->disk->new_file($this->temp_folder, 'mp3');
		//Build command
		$command	= "sox";
		
		//Loop through all characters
		foreach($characters as $file){
			$command = "{$command} {$file->get()}"; //Get location of character
		}
		
		$command	= "{$command} {$new_file}";
		
		shell_exec($command); //Execute command
		
		//Check that file was rendered
		if( file_exists($new_file) && ! is_dir($new_file) ){
			return $new_file;
		}else{
			echo "\n{$this->errors->msg()}\n";
			return false;
		}
	}
	
	
	public function merge($audio_1, $padding_1, $audio_2, $padding_2)
	{
		//Validate that the files exist
		if( ! file_exists ( $audio_1 ) ){
			echo "\n{$this->errors->msg(903)} @ {$audio_1}\n";
			return false;
		}elseif( ! file_exists ( $audio_2 ) ){
			echo "\n{$this->errors->msg(903)} @ {$audio_2}\n";
			return false;
		}
		
		$new_file		= $this->disk->new_file($this->temp_folder, '.mp3');

		shell_exec( " sox -m -v -0.5 {$audio_1} {$audio_2} {$new_file} " ); //Execute command
		
		return $new_file;
	}
	
	
	public function audio_normalize($audio)
	{
		if( ! file_exists($audio) ){	//Check that file exists
			echo "\n{$this->errors->msg()}\n";
			return false;
		}elseif( is_dir($audio) ){		//Check that file is not a directory
			echo "\n{$this->errors->msg()}\n";
			return false;
		}
		
		$new_file		= $this->disk->new_file($this->temp_folder, '.mp3');
		$new_file_2		= $this->disk->new_file($this->temp_folder, '.mp3');
		
		shell_exec( " sox {$audio} -c 2 {$new_file} " );
		shell_exec( " sox {$new_file} {$new_file_2} rate -s -a 44100 dither -s " );
		
		return $new_file_2;
	}
}