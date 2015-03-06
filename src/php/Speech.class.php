<?php

/**
 * Generates Speech Sound Files
 *
 * @name		Speech Generator
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * Dependencies:
 *		cURL PHP Module
 *
 **/

require_once __DIR__ . '/Errors.class.php';
require_once __DIR__ . '/Disk.class.php';
require_once __DIR__ . '/Character.class.php';

class Speech{

	private $errors, $disk, $api_url, $temp_folder, $type, $characters;
	private $global_count, $allowed_types, $api_key, $allowed_gender, $r_engines, $gender, $engine;

	/**
	* Constructor: Initializes necessary members for the class to use
	*
	* @param string $temp_folder 	Folder where all temporary data will be stored
	* @param string $type			Master file extension
	* @param string $gender			Gender of speech generation
	* @param string $engine			Speech engine to use
	*/
	function __construct( $temp_folder, $type='mp3', $gender='female')
	{
		$this->allowed_types		= array('mp3', 'wav', 'ogg');	//Allowed request types
		$this->api_key				= '59e482ac28dd52db23a22aff4ac1d31e';
		$this->allowed_gender		= array('male', 'female');
		$this->characters			= array();
		$this->global_count			= 0;
		$this->allowed_types		= array('mp3', 'wav', 'ogg');	//Allowed request types
		$this->api_key				= '59e482ac28dd52db23a22aff4ac1d31e';
		$this->allowed_gender		= array('male', 'female');
		$this->errors				= new Errors;
		$this->disk					= new Disk;
		
		if( $type == NULL ){	//If null set default
			$type 						= 'mp3';
		}else{
			$type						= strtolower($type);
		}
		$type 						= str_replace('.', '', $type);	//Take off 'dot' from type, in case the user entered '.extension'
		$gender						= strtolower($gender);
		$this->gender				= $gender;

		if( ! is_dir($temp_folder) ){		//Validate that this is a directory
			echo "\n{$this->errors->msg(600)}: {$temp_folder}\n";
			return false;
		}else{
			$this->temp_folder			= $temp_folder;	//Set directory
		}

		if( ! in_array($gender, $this->allowed_gender) ){	//Validate speech gender
			echo "\n{$this->errors->msg(302)}: {$gender}\n";
			return false;
		}

		if( ! in_array($type, $this->allowed_types) ){	//Check that type is valid
			echo "\n{$this->errors->msg(301)}: {$type}\n";
			return false;
		}else{
			$this->type					= $type;	//Set type
			$this->api_url				= "http://api.ispeech.org/api/rest?format={$this->type}&action=convert&apikey={$this->api_key}&speed=1&voice=usenglish{$gender}&text=";
		}
		//Object has been initialized successfully
	}
	
	
	/**
	* iSpeech Fetch: Generates speech files using the iSpeech sound engine
	*
	* @param string $text Text to send to request
	* @return mixed Returns CHARACTER type object with speech, 'false' on failture
    **/
	public function fetch_speech($text)
	{
		$this->global_count  	= 0; //Keeps count of number of sound files being generated
		$failure				= false;
		
		//Make sound clips
		$searchStr 				= ' ';
		$text 					= str_replace($searchStr, '+', $text);  //Find and replace all spaces with '+'
		$inputText 				= array("{$this->api_url}{$text}");


		return $this->curl_fetch($inputText);	
	}


	/**
	* Set API URL: Change default URL for requesting Speech Sound files
	*
	* @param string $url URL of API
    **/
	public function set_api_url($url)
	{
		$this->api_url	= $url;
	}


	/**
	* Set API URL: Change default URL for requesting Speech Sound files
	*
	* @param string $requestURL URL to fetch data from
	* @return
    **/
	private function curl_fetch($requestURL)
	{
		$arrHandles 	= array();
		$mh 			= curl_multi_init();
		$parent			= __DIR__;

		$this->global_count++;

		//Let cURL do its job
		foreach($requestURL as $input){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $input);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
			if( $this->engine == 'ivona' ){
				curl_setopt($ch, CURLOPT_HTTPHEADER, $arrHeader);
				curl_setopt($ch, CURLOPT_COOKIE, $cookie_1);

			}
			
			curl_multi_add_handle($mh, $ch);
			$arrHandles[] = $ch;
		}

		do{
			$mrc = curl_multi_exec($mh, $active);
		}while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($mh) != -1) {
				do{
					$mrc = curl_multi_exec($mh, $active);
				}while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		// Write all handles' content to files
		foreach ($arrHandles as $handle) {
			$new_file			= $this->disk->new_file($this->temp_folder, '.mp3');
			$content 			= curl_multi_getcontent($handle);
			file_put_contents($new_file, $content);

		}

		// Remove handles
		foreach ($arrHandles as $handle)
			{curl_multi_remove_handle($mh, $handle);}

		// Close curl
		curl_multi_close($mh);
	
		return $new_file;

	}

}