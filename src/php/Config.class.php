<?php

class Config{

	private $core, 
			$p_database,
			$storage,
			$script;

	function __construct()
	{
		$this->core				= array(
											'e_reports'	=> 		array(	//Enable email error reports
												'enabled'				=> false,
												'email'					=> 'granados.carlos@gmail.com',
												'subject'				=> 'Media Generator Error'
											),
											'c_errors'	=> 		array(	//Define your custom error class
												'enabled'				=> false,
												'class'					=> 'your_custom_error_class.php',
												'trigger'				=> 'method_to_call_on_error',
												'params'				=> array('array_of_params')
											),
											'uploads'	=> 		array(	//Register upload plugins
												'youtube'				=> array(
																			'enabled'	=>	false,
																			'username'	=>	'youtube_username',
																			'password'	=>	'youtube_password'
												),
												'vimeo'					=> array(
																			'enabled'	=>	false,
																			'username'	=>	'vimeo_username',
																			'password'	=>	'vimeo_password'
												),
												'flickr'				=> array( 
																			0	=> array(
																				'enabled'	=>	false,
																				'username'	=>	'flickr_username',
																				'password'	=>	'flickr_password'
																			),
																			1 	=> array(
																				'enabled'	=>	false,
																				'username'	=>	'flickr_username',
																				'password'	=>	'flickr_password'
																			)
												)
											)
										);

		$this->client_database	= array(
												'core'		=> 	array(	//Database to fetch data from
													'host_name'			=>	'database_host_name',
													'username'			=>	'database_user',
													'password'			=>	'database_password',
													'datbase'			=>	'database_name'
												),
												'schema'	=> 	array(
													'table'				=>	'table_name',
													'id'				=>	'id_field',
													'part_number'		=>	'part_number_field',
													'part_manufacturer'	=>	'manufacturer_field',
													'description'		=>	'description_field'
												)
											);

		$this->storage			= array(
											'core'		=> 	array(	//Database to write video information to
												'enabled'			=>	false,
												'host_name'			=>	'database_host_name',
												'username'			=>	'database_user',
												'password'			=>	'database_password',
												'datbase'			=>	'database_name'
											),
											'schema'	=> 	array(	//Database schema
												'table'				=>	'table_name',
												'id'				=>	'id_field',
												'fk_id'				=>	'product_foreign_key_field',
												'channel'			=>	'internal_video_type_identifier_field',
												'service'			=>	'upload_service_id_field',
												'video_id'			=>	'video_id_field'
											),
											'cache'		=>	array(	//Caching folder
												'enabled'			=>	true,
												'location'			=>	'/var/cache/generator_cache/',
												'persistace'		=>	'* * * * * *'
											),
											'temp'		=>	'/tmp/'
										);
	}


	public function fetch_config($data_item)
	{
		if( ! isset($this->{$data_item}) ){
			return false;
		}else{
			return $this->{$data_item};
		}
	}

}