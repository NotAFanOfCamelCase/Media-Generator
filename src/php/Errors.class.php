<?php

/**
 * Error Messages: Holds all error messages used by the video generator
 *
 * @name		Error Messages
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 *
 * This class serves as the main error class for media generator,
 * or can be used as a wrapper for your custom error class by simply
 * calling the method 'error_assign'.
 **/
 
$lib_dir = realpath(__DIR__ . "/..");
require_once "{$lib_dir}/3party/cli_color/Cli_Color.php";

class Errors{

	private static $color_string, $config, $msg_prefix;
	private $messages, $error_usr;
	
	/* @const MSG_PLACEHOLDER - Used as a placeholder for an error message*/
	const	MSG_PLACEHOLDER		= 'gen_base_self_class_auth_msg';
	/* @const LNE_PLACEHOLDER - Used as a placeholder for the line number of an error*/
	const	LNE_PLACEHOLDER		= 'gen_base_self_class_auth_code';

	function __construct( $user_mode = false )
	{
		$this->color_string		= new Cli_Color;
		$this->msg_prefix		= "(!)MEDIA_GEN- ";
		$this->error_usr		= false;
		$this->temp				= NULL;
		
		//Setup user mode
		if( $user_mode )
		{
			require_once $user_mode;
			$this->config			= new Config;
		}

		/**
		* @var array Strings of error messages divided by different error levels
		* 
		* Error Levels:
		*		0 	 - Class Method Call Error
		*		>0   - Universal Errors
		*		100  - Database 	Object
		*		200  - Sequencer     ''
		*		300  - Speech 	     ''
		*		400  - YouTube 	     ''
		*		500  - Video Render  ''
		*		600  - Disk Interf.  ''
		*		700  - Character	 ''
		*		800  - CSV Parser    ''
		*		900  - Sound Engine  ''
		*		1000 - HookLoader    ''
		*		2000 - Config	     ''
		*		4000 - Tracker		 ''
		*		5000 - Tracking_Data ''
		*		6000 - Flash Parser	 ''
		**/
		$this->messages 		= array(
							0		=> "ERROS::msg() failture - Error code not set at function call",
							1		=> "Invalid parameter supplied @ ",
							200		=> "Sequencer Error",
							201		=> "Time Validation Error",
							202		=> "Error processing file, check that the file exists or that the appropiate extension has been set: ",
							203		=> "No frames were found, check that the correct file extension has been set",
							204		=> "Template failed to load",
							205		=> "The following frames were not found: ",
							206		=> "The starting frame is greater than the total amount of frames",
							207		=> "The starting frame is greater than the ending frame",
							208		=> "The ending frame is greater than the total amount of frames",
							209		=> "CHARACTER::__construct() failture",
							210		=> "Sequencer ended unexpectedly with message: ",
							211		=> "Character does not exist: ",
							212		=> "Sequencer failed to copy file: ",
							213		=> "Failed to export frame: ",
							214		=> "Tween duration cannot be 0",
							215		=> "Frame replication failed at frame(s):",
							216		=> "Invalid argument supplied in SEQUENCER::is_character(). Parameter is not a CHARACTER type object",
							217		=> "A valid set of frames could not be found @ ",
							218		=> "Text failed to generate",
							219		=> "Unable to render to directory, check permissions.",
							220		=> "Failed to include plugin, syntax errors were found: ",
							221		=> "Python extension does not exist:",
							222		=> "Cannot render blank imagik instance",
							300		=> "Speech API server failed to send sound file",
							301		=> "Invalid type set at SOUND::type",
							302		=> "Invalid gender set",
							303		=> "CHARACTER::dimmensions() is not available for sound files",
							304		=> "Invalid sound engine set",
							500		=> "FFMPEG could not be found in your system",
							501		=> "Video rendering failed",
							502		=> "The export path is invalid, make sure the path exists and that you enetered the FULL path",
							503		=> "Renderer::audio_video() invalid parameter. There was a problem with this file: ",
							504		=> "No video has been rendered",
							600		=> "The directory supplied is invalid",
							601		=> "The file supplied does not exist",
							602		=> "Failed to save to directory: ",
							603		=> "The current permissions of this directory do not allow manipulation. 
										\nAdditionally, we've attempted to change the permissions and failed",
							700		=> "Character failed to initialize, invalid type",
							701		=> "Invalid tolerance level set at CHARACTER::__construct()",
							702		=> "Unable to create a temporary folder, check permissions",
							703		=> "Unable to determine video frame rate. Referer @ ",
							704		=> "CHARACTER::constraint() can only be applied to image characters",
							705		=> "Invalid parameter for CHARACTER::v_frame()",
							706		=> "This character does not support frame manipulation. Only video characters have more than one frame",
							900		=> "Sox could not be found in your system",
							901		=> "Audio could not be rendered",
							902		=> "Temporary folder is invalid",
							903		=> "Audio file supplied does not exist, referer",
							1000	=> "Could now load plugin. Plugin does not exist @ ",
							1001	=> "HookLoader failed to load plugin. This plugin has syntax errors @",
							1002	=> "Event does not exist in plugin @ ",
							2000	=> "Data item does not exist in configuration",
							5000	=> "Tracking configuration file does not exist",
							5001	=> "Destination file already exists",
							5002	=> "This is a directory, a file name is required as well",
							6000	=> "Failed to create temporary folder in \"tmp\", check permissions?",
							6001	=> "No tracking points were found in this file, wrong file?",
							6002	=> "UNEXPECTED STRUCTURE: There was an error mapping these tracking points, source file maybe corrupted"
							);
	}


	/**
	* Data Accessor: Returns an error message according to the error code parameter
	* @param int Error code
	* @return string Error message
    */
	public function msg( $code = 0 )
	{
		if( array_key_exists($code, $this->messages) )
		{
			if( $this->error_usr ) //Check for user provided error class
			{
				//Validate params
				$temp_params	= array();
				foreach( $this->error_usr_data[2] as $param )
				{
					if( is_string($param) ){
						if($param == self::MSG_PLACEHOLDER)
						{
							$temp_params[]	= $this->messages[$code];
						}
					}else{
						$temp_params[]	= $param;
					}
				}
				
				//Call method of custom error class
				call_user_func_array( array($this->error_usr_data[0], $this->error_usr_data[1]), $temp_params ); 
			}
			//self::handleError(self::$messages[$code]);
			return $this->color_string->getColoredString("\n{$this->msg_prefix} {$this->messages[$code]}\n", 'red');
		}else
			return $this->color_string->getColoredString("\n{$this->msg_prefix} Invalid error code set\n", 'red');
	}


	private function handleError($error)
	{
		// Email the error and a backtrace to the admin
		$body = "Error reported: {$error}\r\n\r\nBacktrace: " . var_export(debug_backtrace(),true) . "\r\n\r\n";
		$headers = 'From: noreply@launch3.net' . "\r\n";
		mail(CONFIG::get('it_email'), CONFIG::get('def_e_subject'), $body, $headers);
	}


	/**
	* Error Assign: Allows for a custom error class to be used.
	* @param 	object		$errors_obj		Your custom error class
	* @param	string		$trigger		Name of method of your custom class to call for every error
	* @param	array		$parameters		List of parameters the method takes (see SELF::MSG_PLACEHOLDER reference)
	* @return string Error message
    */
	public function error_assign($errors_obj, $trigger = 'msg', $parameters = array(SELF::MSG_PLACEHOLDER))
	{
		//Validate the object************
		if( ! is_object($errors_obj) )
		{
			throw new Exception( "\nAn object was not supplied as first parameter\n" );
			return false;
		}
			//Check trigger
		if( ! method_exists($errors_obj, $trigger) )
		{
			throw new Exception( "\nMethod '{$trigger}' does not exist in class '" . get_class($errors_obj) . "'\n" );
			return false;
		}
			//Check that parameters were supplied as an array
		if( ! is_array($parameters) )
		{
			throw new Exception( "\nParameters list is not an array\n" );
			return false;
		}
		
			//Check number of arguments for function is correct
		$rfl						= new ReflectionMethod( get_class($errors_obj), $trigger );
		
		if( ( count($parameters) ) < $rfl->getNumberOfRequiredParameters() )
		{
			throw new Exception( "\nThe method '{$trigger}' of class '" . get_class($errors_obj) . "' requires ". $rfl->getNumberOfRequiredParameters() . " parameters, you supplied only " . count($parameters) . "\n" );
			return false;
		}

		//All validation finished, set all values
		$this->error_usr			= true;
		$this->error_usr_data		= array( $errors_obj,
											 $trigger,
											 $parameters );
	}

}