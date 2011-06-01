<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * @uses JSMin
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
class Kohana_StaticJs extends StaticFile {

	/**
	 * Class instances
	 *
	 * @static
	 * @var array of StaticJs instances
	 */
	protected static $_instances = array();

	/**
	 * Javascript links and files container
	 *
	 * @var array
	 */
	protected $_js = array();

	/**
	 * StaticFiles config object
	 *
	 * @var Kohana_Config
	 */
	protected $_config;

	/**
	 * Class instance initiating
	 *
	 * @static
	 * @param string $type
	 * @return StaticJs
	 */
	public static function instance($type = 'default')
	{
		if ( ! is_object(Arr::get(self::$_instances, $type, NULL)))
		{
			self::$_instances[$type] = new StaticJs();
		}

		return self::$_instances[$type];
	}

	/**
	 * Adds js file to a js list
	 * $place can be:
	 *    - 'docroot' (default)
	 *    - 'modpath' (try to search in modules)
	 *    - 'inline'
	 *    - 'cdn'
	 *
	 * @param  string      $js
	 * @param  string|null $condition
	 * @param  string      $place
	 * @param  int|null    $id
	 * @return StaticJs
	 */
	public function add($js, $condition = NULL, $place = 'docroot', $id = NULL)
	{
		$js = trim($js, '/');

		switch($place)
		{
			case 'docroot':
				$this->_add_docroot_js($js, $condition);
				break;
			case 'modpath':
				$this->_add_modpath_js($js, $condition);
				break;
			case 'inline':
				$this->_add_inline_js($js, $condition, $id);
				break;
			case 'cdn':
//				$this->_add_library($js,);
		}

		return $this;
	}

	/**
	 * Render all javascript
	 *
	 * @return string
	 */
	public function get_all()
	{
		return $this->get('docroot') . "\n" . $this->get('modpath') . "\n" . $this->get('inline');
	}

	/**
	 * Gets js by it's placement
	 *
	 * @param string $place
	 * @return int|mixed|null|string
	 */
	public function get($place = 'docroot')
	{
		$benchmark = Profiler::start(__CLASS__, __FUNCTION__);

		if ( ! isset($this->_js[$place]) OR ! count($this->_js[$place]))
		{
			Profiler::stop($benchmark);
			return NULL;
		}

		$js_code = '';
		if($place == 'inline')
		{
			foreach ($this->_js['inline'] as $condition => $js_array)
			{
				foreach ($js_array as $id => $js)
				{
					if ($this->_config->js['min'])
					{
						$js = JSMin::minify($js);
					}
					$js_code .= $js;
				}
			}

			$js_code = $this->prepare_js_anchors($js_code);

			if ( ! $this->_config->js['build'])
				return '<script language="JavaScript" type="text/javascript">' . trim($js_code) . '</script>';
		}


		// Not need to build one js file
		if ( ! $this->_config->js['build'])
		{
			foreach ($this->_js[$place] as $condition => $js_array)
			{
				if($place !== 'inline')
				{
					foreach($js_array as $js => $condition)
					{
						$js_code .= $this->get_link($js, $condition) . "\n";
					}
				}
				else
				{
					continue;
				}
			}
		}
		else
		{
			if($place == 'inline')
			{
				// If one file building of inline scripts is needed
				$build_name = $this->make_file_name($this->_js['inline'], 'inline', 'js');
				if ( ! file_exists($this->cache_file($build_name)))
				{
					$this->save($this->cache_file($build_name), $js_code);
				}

				$js_code = $this->get_link($this->cache_url($build_name));
			}
			else
			{
				$build = array();
				foreach ($this->_js[$place] as $condition => $js_array)
				{
					foreach($js_array as $js => $condition)
					{
						$build[$condition][] = $js;
					}
				}

				foreach ($build as $condition => $js)
				{
					$build_name = $this->make_file_name($js, $condition, 'js');

					// Checking Cache file TTL
					$this->_cache_ttl_check($build_name);

					if ( ! file_exists($this->cache_file($build_name)))
					{
						$build_content = '';
						if($place === 'inline')
						{
							$build_content .= $js_code;
						}
						else
						{
							// first time building
							foreach ($js as $url)
							{
								$_js = $this->get_source($url, $place);

								// look if file name has 'min' suffix to avoid extra minification
								if ($this->_config->js['min'] AND (! mb_strpos($url, '.min.') AND ! mb_strpos($url, '.pack.')))
								{
									$_js = JSMin::minify($_js);
								}

								$build_content .= $_js;
							}
						}

						$this->save($this->cache_file($build_name), $build_content);
					}

					$js_code .= $this->get_link($this->cache_url($build_name), $condition);
				}
			}
		}

		Profiler::stop($benchmark);
		return $js_code;
	}

	/**
	 * Adds js files that can be found in js directory in DOCROOT
	 *
	 * @param  string      $js
	 * @param  string|null $condition
	 * @return void
	 */
	protected function _add_docroot_js($js, $condition = NULL)
	{
		$this->_js['docroot'][$condition][$js] = $condition;
	}

	/**
	 * Adds js files that can be found in js directory in MODPATH
	 *
	 * @param  string      $js
	 * @param  string|null $condition
	 * @return void
	 */
	protected function _add_modpath_js($js, $condition = NULL)
	{
		$js = $this->_config->temp_docroot_path . $js;
		$this->_js['modpath'][$condition][$js] = $condition;
	}

	/**
	 * Adds inline js
	 *
	 * @param  $js
	 * @param null $condition
	 * @param null $id
	 * @return void
	 */
	protected function _add_inline_js($js, $condition = NULL, $id = NULL)
	{
		if ($id !== NULL)
		{
			$this->_js['inline'][$condition][$id] = $js;
		}
		else
		{
			$this->_js['inline'][$condition][] = $js;
		}
	}

	/**
	 * Gets html code of the script loading
	 *
	 * @param  string $js
	 * @param  string|null $condition
	 * @return string
	 */
	protected function get_link($js, $condition = NULL)
	{
		$js = trim($js, '/');
		if (mb_substr($js, 0, 4) != 'http')
		{
			$js = ($this->_config->host == '/') ? $js : $this->_config->host . $js;
		}

		return ($condition ? '<!--[if ' . $condition . ']>' : '')
		     . HTML::script($js)
		     . ($condition ? '<![endif]-->' : '') . "\n";
	}

	/**
	 * Prepares javascript code
	 *
	 * @param  string $js_code
	 * @return mixed
	 */
	protected function prepare_js_anchors($js_code)
	{
		return str_replace('{staticfiles_url}', STATICFILES_URL, $js_code);
	}

	/**
	 * Loads library from CDN
	 *
	 * @param  $lib_name
	 * @param  $version
	 * @return void
	 */
	public function load_library($lib_name, $version)
	{
		$this->add('https://ajax.googleapis.com/ajax/libs/'.$lib_name.'/'.$version.'.min.js', NULL, 'cdn');
	}

} // End Kohana_StaticJs