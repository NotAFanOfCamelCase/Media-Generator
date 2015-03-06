<?php

require_once __DIR__ . '/Errors.class.php';

class TrackingData
{

	private $coordinates, $start, $errors, $file, $start_counter;


	function __construct($con_file=null, $coordinates=null, $start=null)
	{
		$this->errors			= new Errors;
		$this->file				= $con_file;
		$this->coordinates		= array();
		
		if( $con_file == null ){	//If not do manual setup
			//Validate variables
				if( ! is_array($coordinates) ){
					echo "\n{$this->errors->msg()}\n";
				}
			$this->coordinates		= $coordinates;
			$this->start			= $start;
		}elseif( ! $this->config_parse() ){ //Try to initialize object with configuration file
			return false;
		}
		
	}


	private function config_parse()
	{
		if( ! file_exists( $this->file ) ){
			echo "\n{$this->errors->msg(5000)}\n";
			return false;
		}

		$file_handle 			= fopen($this->file, "r");
		$record_loc  			= false;

		while (!feof($file_handle))
		{
		   $line = fgets($file_handle);
		   $str	 = str_split($line);

		   if( ! ($str[0] == '#') )	//Ignore lines that begin with '#'
		   {
				$target_remov		= strstr($line, '#');	//Strip out comments from lines with relevant data
				$line				= str_replace($target_remov, '', $line);

				if( strpos($line,'@START') !== false )
				{
					$this->start			= intval(str_replace(' ', '', strstr($line, ' ')));
					$this->start_counter	= $this->start;
				}
				elseif( strpos($line,'@LOCATION') !== false )
				{
					$record_loc				= true;
				}
				elseif( $record_loc == true )
				{
					if( strpos($line,'@FALSE') !== false ){	//Check if coordinates are available
						$this->coordinates[$this->start_counter]	= false;
					}
					else{
						$temp_arr				= explode(',', $line);
						foreach( $temp_arr as &$int ){
							$int	= intval(str_replace(' ', '', $int));
						}
						$this->coordinates[$this->start_counter]	= $temp_arr;
						$this->start_counter++;
					}
				}
			}
		}

		fclose($file_handle);
	}
	
	
	public function save($destination)
	{
		if( file_exists($destination) ){
			echo "\n{$this->errors->msg(5001)} @ {$destination}\n";
			return false;
		}elseif( is_dir($destination) ){
			echo "\n{$this->errors->msg(5002)} @ {$destination}\n";
			return false;
		}
		
		$handle		= fopen($destination, 'w');
		$date		= date("m/d/Y");

		fwrite($handle, "# TWEEN CONFIGURATION FILE\n");
		fwrite($handle, "# Exported on {$date}\n\n");
		fwrite($handle, "@START {$this->start}\n\n");
		fwrite($handle, "# FRAME DATA START\n");
		fwrite($handle, "@LOCATION\n");
		
		foreach( $this->coordinates as $pair ){
			if( $pair == false ){
				fwrite($handle, "@FALSE\n");
			}else{
				fwrite($handle, "{$pair[0]}, {$pair[1]}\n");
			}
		}

		fclose($handle);
	}
}