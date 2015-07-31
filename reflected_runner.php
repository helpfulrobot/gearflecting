#!/usr/bin/php
<?php

include_once __DIR__.'/vendor/autoload.php';

/**
 * Ensure that people can't access this from a web-server
 */
if(PHP_SAPI != 'cli') {
	die();
}

/**
 * Identify the cli-script.php file and change to its container directory, so that require_once() works
 */
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

/**
 * Process arguments and load them into the $_GET and $_REQUEST arrays
 * For example,
 * sake my/url somearg otherarg key=val --otherkey=val third=val&fourth=val
 *
 * Will result int he following get data:
 *   args => array('somearg', 'otherarg'),
 *   key => val
 *   otherkey => val
 *   third => val
 *   fourth => val
 */
if(isset($_SERVER['argv'][2])) {
    $args = array_slice($_SERVER['argv'],2);
    if(!isset($_GET)) $_GET = array();
    if(!isset($_REQUEST)) $_REQUEST = array();
    foreach($args as $arg) {
       if(strpos($arg,'=') == false) {
           $_GET['args'][] = $arg;
       } else {
           $newItems = array();
           parse_str( (substr($arg,0,2) == '--') ? substr($arg,2) : $arg, $newItems );
           $_GET = array_merge($_GET, $newItems);
       }
    }
  $_REQUEST = array_merge($_REQUEST, $_GET);
}

// Set 'url' GET parameter
if(isset($_SERVER['argv'][1])) {
	$_REQUEST['url'] = $_SERVER['argv'][1];
	$_GET['url'] = $_SERVER['argv'][1];
}

$function = function ($args) {
	o('Running gearman job');
	
	$raw = @unserialize($args);
	
	if (isset($raw[0])) {
		$path = $raw[0];
		$based = base64_encode($args);
		
		$cmd = $path . '/framework/cli-script.php';
		o('Executing against ' . $cmd);
		
		if (file_exists($cmd)) {
			$cmd = "php $cmd dev/tasks/GearmanJobTask gearman_data=" . escapeshellarg($based);
			$output = `$cmd`;

			o("Job complete, memory used " . memory_get_usage());
			return;
		}
	} else {
		o("Discarding job with args $args");
	}
};

$worker = new \Net\Gearman\Worker();
$worker->addServer();
$worker->addFunction('silverstripe_handler', $function);
$worker->work();

function o($m) {
	echo date('[Y-m-d H:i:s]') . " $m " . "\n";
}