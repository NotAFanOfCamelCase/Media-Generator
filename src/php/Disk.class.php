<?php

/**
 * Handles Disk Operations
 *
 * @name		Disk Interface
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 **/
 
require_once realpath(__DIR__ ) . '/Base.class.php';
require_once realpath(__DIR__ ) . '/Errors.class.php';
 
class Disk extends Base{


	private $root_directory			= __DIR__;
	private $errors;

	function __construct()
	{
		$this->errors				= new Errors;
	}
	
	/**
	* Directory Discover: Get all files of type 'x' from directory 'y'
	* @param string Directory path
	* @param string Type of files to look for
	* @return mixed The array with the files discovered, 'false' on failture
	**/
	public function discover_dir($path, $type)
	{
		$type 				= str_replace('.', '', $type);

		if( ! file_exists($path) ){			//Check that the directory is valid
			echo "\n{$this->errors->msg(600)}\n";
			return false;
		}

		$frame_files		= glob("{$path}/*{.{$type}}", GLOB_BRACE);	//Fetch files
		sort($frame_files);												//Order frames

		return $frame_files;		//Return array with file names and paths
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
	* Validate File: Checks that path supplied exists, and that it is a file and not a directory
	* @param array Path to subject
	* @return bool 'True' if found, 'false' otherwise
	* @throws exception If a files is not found the message will contain the files that weren't found
    **/
	public function validate_file($path)
	{
		$failed				= false;

		if( ! file_exists($path) ){	//Check that the file exists
			$not_found		= "{$path} - File Not Found"; 
			$failed			= true;		//Indicate that we have failed to make use of this file
		}
		
		if( is_dir($path) ){	//Check if it's a directory
			$not_found		= "{$path} - This is a directory";
			$failed			= true;		//Indicate that we have failed to make use of this file
		}
		
		if( $failed ){
			throw new Exception($not_found);
			return false;
		}
		
		return true;
	}
	
	
	/**
	* Validate File: Examines the supplied path and generates a valid temporary file name. 
	* @param string Path of folder to examine
	* @param string Extension of file to use
	* @return mixed Returns the name of the file if successful, 'false' otherwise
    **/	
	public function new_file( $location, $type='')
	{
		$type = str_replace('.', '', $type);		//Take off 'dot' in case the parameter was set with one

		if( is_dir($location) && file_exists($location) ){
			$temp_name = tempnam($location, '');	//Generate unique file
			if( $temp_name ){
					shell_exec(" rm {$temp_name} ");		//Delete random file
			} else {
				return false;
			}
			return "{$temp_name}.{$type}";			//Send back the name of the file
		}else{
			echo "\n{$this->errors->msg(602)}{$location}\n";
			return false;
		}
	}
	
	
	/**
	* Validate Directory: Determines the existence and appropiate permission of the path supplied
	* @param string Path of folder to examine
	* @return mixed Returns the name of the file if successful, 'false' otherwise
	* @throws Exception On failture throws error code
    **/	
	public function validate_dir( $directory )
	{
		if( ! is_dir($directory) ){	//First lest check that the directory exists
			throw new Exception('600');
			return false;
		}
		
		if( ! (fileperms($directory) == '0777' ) ){ //Check that directory's permissions
			//Attempt to change the permissions
			if( ! chmod( $directory, 0777 ) ){
				throw new Exception('603');
				return false;
			}
		}
		
		return true;
	}

}