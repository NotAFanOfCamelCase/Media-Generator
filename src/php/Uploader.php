<?php

/**
 * Predefines uploader plugin classes
 *
 * @name		Uploader Abstract
 * @package    	Media Generator 2.0
 * @subpackage 	Library
 * @author     	Carlos Granados <granados.carlos91@gmail.com>
 */	

abstract class Uploader {
	
	abstract protected function authenticate();
	abstract protected function upload();
	abstract protected function delete();
	//function download();
	
}