<?php defined('SYSPATH') or die('No direct script access.');

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

require_once Kohana::find_file('vendor/jsmin', 'jsmin');

define('STATICFILES_URL', $config->host . $config->url);