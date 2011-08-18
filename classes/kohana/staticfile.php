<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Main StaticFile class
 *
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
class Kohana_StaticFile {

	/**
	 * @var Kohana_Config
	 */
	protected $_config;

	/**
	 * Javascript links and files container
	 *
	 * @var array
	 */
	protected $_js = array(
		'docroot' => array(),
		'modpath' => array(),
		'inline' => array(),
	);

	/**
	 * CSS links and files container
	 *
	 * @var array
	 */
	protected $_css = array(
		'docroot' => array(),
		'modpath' => array(),
		'inline' => array(),
	);

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		$this->_config = Kohana::config('staticfiles');
	}

	/**
	 * Blocking mechanism of file saving
	 *
	 * @see http://forum.dklab.ru/viewtopic.php?p=96622#96622
	 * @param  string $file
	 * @param  string $data
	 * @return void
	 */
	function save($file, $data)
	{
		// Creating empty file if it is not exists
		// If exists - this operation will make no harm to it
		fclose(fopen($file, "a+b"));

		// File blocking
		if(! ($f = fopen($file, "r+b")))
		{
			throw new Kohana_Exception(__('Can\'t open cache file!'));
		}

		// Waiting a monopole owning
		flock($f, LOCK_EX);

		// Writing file
		fwrite($f, $data);

		fclose($f);
	}

	public function get_source($url, $place = 'docroot')
	{
		$file_path = NULL;
		switch($place)
		{
			case 'docroot':
				$file_path = DOCROOT . str_replace('/', DIRECTORY_SEPARATOR, $url);
				break;
			case 'modpath':
				$pathinfo = pathinfo(trim(str_replace('/', DIRECTORY_SEPARATOR,  str_replace('media', '', $url)), DIRECTORY_SEPARATOR));
				$file_path = Kohana::find_file('media', $pathinfo['dirname'].DIRECTORY_SEPARATOR.$pathinfo['filename'], $pathinfo['extension']);
				break;
		}

		return ($file_path) ? file_get_contents($file_path) : NULL;
	}

	/**
	 * Returns cache file path
	 *
	 * @param  string $file_name
	 * @return string
	 */
	public function cache_file($file_name)
	{
		$cache_file = $this->_config->path
		              . preg_replace('/\//', DIRECTORY_SEPARATOR, $this->_config->cache)
		              . preg_replace('/\//', DIRECTORY_SEPARATOR, $file_name);

		if ( ! file_exists(dirname($cache_file)))
		{
			mkdir(dirname($cache_file), 0755, TRUE);
		}

		return $cache_file;
	}

	/**
	 * Returns cache file url
	 *
	 * @param  string $file_name
	 * @return string
	 */
	public function cache_url($file_name)
	{
		return $this->_config->cache . $file_name;
	}

	/**
	 * Render all objects of a StaticFile class instance (js or css)
	 *
	 * @return string
	 */
	public function get_all()
	{
		return $this->load_library() . "\n" . $this->get('docroot') . "\n" . $this->get('modpath') . "\n" . $this->get('inline');
	}

	/**
	 * Adds js or css file to a js or css container
	 * $place can be:
	 *    - 'docroot' (default)
	 *    - 'modpath' (try to search in modules)
	 *    - 'inline'
	 *    - 'cdn'
	 *
	 * @todo work with cdn
	 * @param  string      $type
	 * @param  string      $href
	 * @param  string|null $condition
	 * @param  string      $place
	 * @param  int|null    $id
	 * @return void
	 */
	protected function _add($type, $href, $condition = NULL, $place = 'docroot', $id = NULL)
	{
		$href = trim($href, '/');

		switch($place)
		{
			case 'docroot':
				$this->_add_as_docroot($type, $href, $condition);
				break;
			case 'modpath':
				$this->_add_as_modpath($type, $href, $condition);
				break;
			case 'inline':
				$this->_add_as_inline($type, $href, $condition, $id);
				break;
			case 'cdn':
				$this->_add_as_cdn($type, $href);
				break;
		}
	}

	/**
	 * Adds $type files that can be found in $type directory in DOCROOT to $type container
	 *
	 * @param string      $type
	 * @param string      $href
	 * @param string|null $condition
	 * @return void
	 */
	protected function _add_as_docroot($type, $href, $condition = NULL)
	{
		$container = '_'.$type;
		if( ! in_array($href, array_values(Arr::get($this->{$container}['docroot'], $condition, array()))))
			$this->{$container}['docroot'][$condition][] = $href;
	}

	/**
	 * Adds $type files that can be found in $type directory in MODPATH to $type container
	 *
	 * @param  string      $type
	 * @param  string      $href
	 * @param  string|null $condition
	 * @return void
	 */
	protected function _add_as_modpath($type, $href, $condition = NULL)
	{
		$href = $this->_config->temp_docroot_path . $href;
		$container = '_'.$type;
		$this->{$container}['modpath'][$condition][] = array($href => $condition);
	}

	/**
	 * Adds inline code
	 *
	 * @param  string      $type
	 * @param  string      $code
	 * @param  string|null $condition
	 * @param  int|null    $id
	 * @return void
	 */
	protected function _add_as_inline($type, $code, $condition = NULL, $id = NULL)
	{
		$container = '_'.$type;

		$code = $this->_prepare($code, $type);

		if ($id !== NULL)
		{
			$this->{$container}['inline'][$condition][$id] = $code;
		}
		else
		{
			$this->{$container}['inline'][$condition][] = $code;
		}
	}

	protected function _add_as_cdn($type, $href)
	{
		$container = '_'.$type;
		$this->{$container}['cdn'][] = $href;
	}

	/**
	 * Gets html code of the anchor
	 *
	 * @param  string      $type css or js
	 * @param  string      $href
	 * @param  string|null $condition
	 * @return string
	 */
	protected function _get_link($type, $href, $condition = NULL)
	{
		$href = trim($href, '/');
		if (mb_substr($href, 0, 4) != 'http')
		{
			$js = ($this->_config->host == '/') ? $href : $this->_config->host . $href;
		}

		$anchor = NULL;
		switch($type)
		{
			case 'js':
				$anchor = HTML::script($href);
				break;
			case 'css':
				$anchor = HTML::style($href, array('media' => 'all'));
				break;
		}

		return ($condition ? '<!--[if ' . $condition . ']>' : '')
		     . $anchor
		     . ($condition ? '<![endif]-->' : '') . "\n";
	}

	/**
	 * Prepares code before deploying:
	 *  - changes stubs to a STATICFILES_URL
	 *  - minifying code
	 *
	 * @param  string $code
	 * @param  string $type
	 * @return string
	 */
	protected function _prepare($code, $type)
	{
		$code = str_replace('{staticfiles_url}', STATICFILES_URL, $code);

		if($type === 'css')
		{
			if ($this->_config->css['min'])
			{
				$code = $this->minify($code);
			}
		}

		return trim($code);
	}

	/**
	 * Generates unique file name for a build file
	 *
	 * @param  array       $file_array
	 * @param  string|null $condition_prefix
	 * @param  string      $type (css|js)
	 * @return string
	 */
	protected function _make_file_name(array $file_array, $condition_prefix = NULL, $type)
	{
		$condition_prefix = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '-', $condition_prefix));
		$condition_prefix = $condition_prefix ? ($condition_prefix . '/') : '';
		$file_name        = md5($this->_config->host . serialize($file_array));

		return $type . '/'
			 . $condition_prefix
			 . substr($file_name, 0, 1) . '/'
			 . substr($file_name, 1, 1) . '/'
			 . $file_name . '.' . $type;
	}

	/**
	 * Clearing cache if expires its time to live
	 *
	 * @param  $build_name
	 * @return void
	 */
	protected function _cache_ttl_check($build_name)
	{
		if(file_exists($this->cache_file($build_name))
		   AND (filemtime($this->cache_file($build_name)) + $this->_config->cache_reset_interval) < time())
		{
			$this->_cache_reset();
		}
	}

	/**
	 * Clearing cache folders
	 *
	 * @return void
	 */
	protected function _cache_reset()
	{
		$cache_paths = array($this->_config->cache, $this->_config->url);

		foreach($cache_paths as $path)
		{
			$path = DOCROOT . trim(preg_replace('/\//', DIRECTORY_SEPARATOR, $path), '\\');
			File::rmdir($path, TRUE);
		}
	}

} // End Kohana_StaticFile