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
		$this->_add('js', $js, $condition, $place, $id);

		return $this;
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

			$js_code = $this->_prepare($js_code, 'js');

			if ( ! $this->_config->js['build'])
				return '<script language="JavaScript" type="text/javascript">' . trim($js_code) . '</script>';
		}


		// Not need to build one js file
		if ( ! $this->_config->js['build'])
		{
			foreach ($this->_js[$place] as $condition => $js_array)
			{
				foreach($js_array as $js => $condition)
				{
					$js_code .= $this->_get_link('js', $js, $condition) . "\n";
				}
			}
		}
		else
		{
			if($place == 'inline')
			{
				// If one file building of inline scripts is needed
				$build_name = $this->_make_file_name($this->_js['inline'], 'inline', 'js');
				if ( ! file_exists($this->cache_file($build_name)))
				{
					$this->save($this->cache_file($build_name), $js_code);
				}

				$js_code = $this->_get_link('js', $this->cache_url($build_name));
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
					$build_name = $this->_make_file_name($js, $condition, 'js');

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

					$js_code .= $this->_get_link('js', $this->cache_url($build_name), $condition);
				}
			}
		}

		Profiler::stop($benchmark);
		return $js_code;
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