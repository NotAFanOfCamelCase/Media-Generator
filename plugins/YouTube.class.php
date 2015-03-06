<?php

/**
 * Handles YouTube Actions
 *
 * @name		YouTube Control Class
 * @package    	Media Generator 2.0
 * @subpackage 	Plugins
 * @author     	Carlos Granados <carlos@launch3.net>
 */	


//@include Zend Loader
require_once 'Zend/Loader.php';
require_once realpath(__DIR__) . '/../lib/php/Uploaders.class.php'; //Base class
Zend_Loader::loadClass('Zend_Gdata_YouTube');
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');

class YouTube extends Uploader {

	# ACCOUNT CREDENTIALS #
	private $username				= false;
	private $password				= false;
	private $dev_key				= false;
	private $auth_session			= null;

	# MEDIA DETAILS #
	private $media_location			= false;
	private $description			= false;
	private $tags					= false;
	private $title					= false;
	private $video_type				= 'video/mp4';

	# RETURNED #
	private $youtube_id				= false;
	
	#Video Information#
	private $upload_data;


	/**
	* Constructor: Allow quick YouTube connection when declaring YouTube object
	* @param string $username Username of YouTube Account
	* @param string $password Password of Account
	* @param string $dev_key Google Developer Key 
    */
	function __construct( $username, $password, $dev_key )
	{
		$this->username				= $username;
		$this->password				= $password;
		$this->dev_key				= $dev_key;

		//Begin session
		$this->auth_session			= $this->authenticate();
	}


	/**
    * Upload Video
    * @return mixed Return YouTube Video ID on success, returns false otherwise
    */
	public function upload($location, $title, $description, $tags, $category='Education')
	{
		if ( ! file_exists($location) )
		{
			throw new Exception('File ' . $location . ' does not exist');
		}
		elseif( is_dir($location) )
		{
			throw new Exception('This is a directory... You\'re kidding right?');
		}

		$uploadUrl 				= 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads';
		$myVideoEntry 			= new Zend_Gdata_YouTube_VideoEntry();
		$filesource 			= $this->auth_session->newMediaFileSource($this->upload_data['file_location']); 	// create a new Zend_Gdata_App_MediaFileSource object
		
		# SET MEDIA PROPERTIES #
		$filesource->setContentType($this->video_type);
		$filesource->setSlug($this->upload_data['file_location']);				// set slug header
		
		# SET VIDEO PROPERTIES #
		$myVideoEntry->setMediaSource($filesource);					// add the filesource to the video entry
		$myVideoEntry->setVideoTitle($this->upload_data['title']);
		$myVideoEntry->setVideoDescription($this->upload_data['description']);
		$myVideoEntry->setVideoCategory($this->upload_data['category']);
		$myVideoEntry->SetVideoTags($this->upload_data['tags']);

		// Try to upload the video, catching a Zend_Gdata_App_HttpException, 
		// if available, or just a regular Zend_Gdata_App_Exception otherwise
		try
		{
			$newEntry 	= $this->auth_session->insertEntry($myVideoEntry, $uploadUrl, 'Zend_Gdata_YouTube_VideoEntry');
			$videoID 	= $newEntry->getVideoId();
			return $videoID;
		}
		catch (Zend_Gdata_App_HttpException $httpException)
		{
			echo $httpException->getRawResponseBody();
		}
		catch (Zend_Gdata_App_Exception $e)
		{
			echo $e->getMessage();
		}

		return true;
	}


	/**
    * Authenticate & Connect to YouTube
    * @return Zend_Gdata_YouTube Connection object
    */
	private function authenticate()
	{
		$yt						= new Zend_Gdata_YouTube();
		$developerKey			= $this->dev_key;
		$authenticationURL		= 'https://www.google.com/accounts/ClientLogin';
		$applicationId			= 'MediaGenerator';
		$clientId				= 'Media Generator v2';
		$yt->setMajorProtocolVersion(2);
		$httpClient				=  Zend_Gdata_ClientLogin::getHttpClient(
									  $username 	= $this->username,
									  $password 	= $this->password,
									  $service 		= 'YouTube',
									  $client 		= null,
									  $source 		= 'locahost', 
									  $loginToken 	= null,
									  $loginCaptcha = null,
									  $authenticationURL);  
		$session 				= new Zend_Gdata_YouTube( $httpClient, $applicationId, $clientId, $developerKey );

		return $session;
	}
	
	
	/**
    * Delete Specified Video
    * @return void Displays confirmation message
    */
	public function delete()
	{
		$videoEntryToDelete 	= $this->auth_session->getVideoEntry($this->youtube_id, null, true);
			
		try {
			$this->auth_session->delete($videoEntryToDelete);
		}
		catch (Zend_Gdata_App_HttpException $httpException) {
		  echo $httpException->getRawResponseBody();
		}
		catch (Zend_Gdata_App_Exception $e) {
			echo $e->getMessage();
		}
	}
}