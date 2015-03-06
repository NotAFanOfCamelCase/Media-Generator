<?php

/**	
 * Parses Adobe Flash CS5.5 Documents
 *	
 * @name		Flash
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Dependencies:
 *		PHP DOMDocument
 *
 * .FLA files are nothing more than .ZIP
 * files in disguise. Convert them, extract them,
 * find all the goodies :D
 *
 */	
	
	
// @import EZSQL Class
$lib_dir = realpath(__DIR__ . "/..");
require_once realpath(__DIR__) . "/Errors.class.php";
require_once realpath(__DIR__) . "/Disk.class.php";
	
class Flash{

	private $flash_file,
			$disk,
			$trackers,
			$temp_directory,
			$project_directory,
			$default_tracker,
			$DOM_xml_data;


	/**
	* Constructor: Initializes objects
	* @param array Database credentials
    */
	function __construct( $flash_file )
	{
		$this->errors				= new Errors();
		$this->disk					= new Disk();
		$this->default_tracker		= '_track.point';
		try{
			$this->disk->validate_file($flash_file);
		}catch( Exception $e ){
			throw new Exception($e->getMessage());
			return false;
		}
		$this->flash_file			= realpath($flash_file);
		//Start extracting .fla data
		$this->fla_convert();
		$this->DOM_xml_data			= new DOMDocument();
		$this->trackers				= $this->get_trackers();
		if( ! $this->trackers )	//Validate tracking points
		{
			throw new Exception($this->errors->msg(6001) . " @ {$flash_file}");
		}
		
		//Begin track points mapping
		if( ! $this->map_trackers() )
		{
			throw new Exception($this->errors->msg(6002) . " @ {$flash_file}");
		}
	}
	
	
	private function get_trackers()
	{
		//Scan for movieclips
		$tmp_library				= $this->disk->discover_dir(realpath($this->project_directory . '/LIBRARY/'), 'xml');
		$trackers					= array();
		
		if( ! is_array($trackers) )
		{
			return false;
		}
		
		//Save trackers to memory
		foreach( $tmp_library as $xml )
		{
			if( ! strpos($xml, $this->default_tracker) == 0 )
			{
				$trackers[]		= $xml;
			}
		}
		
		if( count($trackers) == 0 )
		{
			return false;
		}

		return $trackers;
	}

	
	private function fla_convert()
	{
		$this->temp_directory		= $this->disk->new_file('/tmp/'); //Return temp directory path on success
		if( $this->temp_directory == false ){
			throw new Exception( $this->errors->msg(6000) );
			return false;
		}
		$this->temp_directory		= $this->temp_directory;
		//Create temp dir
		mkdir($this->temp_directory);

		//Convert .fla files
		shell_exec("cp {$this->flash_file} {$this->temp_directory}/" . basename($this->flash_file, '.fla') . ".zip"); //Copy as .ZIP
		shell_exec("unzip {$this->temp_directory}/" . basename($this->flash_file, '.fla') . ".zip -d {$this->temp_directory}/" . basename($this->flash_file, '.fla') . "/"); //Unzip
		shell_exec("rm -rf {$this->temp_directory}/" . basename($this->flash_file, '.fla') . ".zip"); //Delete zip file
		
		$this->project_directory	= realpath($this->temp_directory . '/' . basename($this->flash_file, '.fla'));
	}
	
	
	private function map_trackers()
	{
		foreach( $this->trackers as $tracker )
		{
			//Loop through XML data and find all trackers
		}
	}

}