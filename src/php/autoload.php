<?php

spl_autoload_register(function($class_name)
{
	require_once realpath(__DIR__) . '/' . $class_name . '.class.php';
});