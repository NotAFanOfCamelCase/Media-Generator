<?php

$lib_dir		= realdir(__DIR__);

require_once $lib_dir . '/Errors.class.php';
require_once $lib_dir . '/Disk.class.php';

class Cleanup extends Disk{

	private $c_files	= array();

	public function track( $file )
	{
		if( ! $this->validate_file( $file ) ){
			$c_files['folders'][]	= $file;
		}else{
			$c_files['files'][]		= $file;
		}
	}

	public function run($param='all')
	{
		//Clean up directories
		if( $param == 'folders' || $param == 'all' ){
			
		}

		//Clean up files
		if( $param == 'files' || $param =='all' ){
			foreach( $this->c_files['files'] as $file ){
			
				$file_location	= pathinfo($file, PATHINFO_DIRNAME );
			
				//Check permissions
				if( ! $this->permission($file_location) ){
					//Change permissions if permissions failed
					$this->permit($file_location);
				}
				
				shell_exec(" rm {$file} "); //Delete file from shell

			}
		}
	}

}