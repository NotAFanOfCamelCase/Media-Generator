<?php

/**
 * Holds location of frames
 *
 * @name		Frame Accessor
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 **/
 
require_once realpath(__DIR__) . '/Errors.class.php';

class Frame{

	private $location	= null;
	private $history	= array(); //Stores history of frames
	private $base_imgk	= false;
	
	function __construct()
	{	
		$this->errors		= new Errors;
	}


	/**
	* New Frame: Insert location of new frame to $location
	* @param string Address of frame in the machine
	* @return bool 'False' if location is invalid, 'true' othervise
    **/
	public function insert( $frame, $type )
	{	//Check that the files exists and that it has the appropiate extension
		if( ! file_exists( $frame ) || pathinfo($frame, PATHINFO_EXTENSION) != $type )	
			{return false;}
		
		$this->location		= $frame; //Insert location of new frame
		$this->history[]	= $frame; //Store frame in history
		return true;
	}


	/**
	* Get Frame: Show location in the machine of the frame in the 'Frame' object
	* @return string Location
	**/
	public function get_frame()
	{
		return $this->location;
	}


	/**
	* Dump History: 'Echos' location of past frames
	**/
	public function dump_history()
	{
		foreach( $this->history as $frame ){
			echo "\n{$frame}";
		}
	}


	/**
	* Get History: Fetches history array
	* @return array Returns history array
	**/
	public function get_history()
	{
		return $this->history;
	}


	/**
	* Stages changes to a picture to be rendered at a later time
	* @param $imgkobj 	Imagick object of 'Character' to compose with
	* @param $x			'X' target coordinate for 'Character'
	* @param $y			'Y' target coordinate for 'Character'
	**/
	public function composer( $imgkobj, $x, $y )
	{
		if( ! $this->base_imgk ){	//Create imagick object to compose
			$this->base_imgk	= new Imagick(realpath($this->location));	//Create object if not
		}
		
		return $this->base_imgk->compositeImage( $imgkobj, $imgkobj->getImageCompose(), $x, $y ); //Compose images
	}


	/**
	* Renders stages changes to the frame
	* @param $file_name File name & destination for rendered frame
	* @return string Location of frame
	**/
	public function render($file_name)
	{
		if( ! $this->base_imgk ){ //Checks pending imagick compositions
			return $this->location;; //If no imagick compositions are pending exit
		}

		$this->base_imgk->writeImage($file_name); //Write image
		$this->location	= $file_name;			//Set 'location' to new image
		return $this->location;
	}

}