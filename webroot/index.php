<?php
/**
 * Main index file for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

if (!defined('APP_DIR')) {
	define('APP_DIR', dirname(dirname(__FILE__)).'/');
}

if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', dirname(__FILE__).'/');
}

if (!include(APP_DIR.'config/config.php')) {
	echo 'Config file not found. Please check that the config file exists and is readable.';
	exit;
}

if (!include($config['client']['path'].'init.php')) {
	echo 'Client files not found. Please check your configuration file.';
	exit;
}

if (!include(APP_DIR.'config/routes.php')) {
	echo 'Routing configuration not found. Please check that the routes file exists and is readable.';
	exit;
}

Dispatcher::dispatch();

/* End of file index.php */