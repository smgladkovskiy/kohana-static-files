<?php defined('SYSPATH') or die('No direct script access.');

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/
require __DIR__.'vendor/autoload.php';

/**
 * @package Kohana-static-files
 */

$config = Kohana::$config->load('static-files');

Route::set('static_files', trim($config->url, '/').'/<file>', array(
	'file'=>'.+'
))
->defaults(array(
	'controller' => 'staticfiles',
	'action' => 'index'
	)
);

if( ! class_exists('JSMin'))
{
	throw new Kohana_Exception("Unable to find library jsmin for module kohana-static-files, please check the vendor directory!");
}

define('STATICFILES_URL', $config->host . $config->url);