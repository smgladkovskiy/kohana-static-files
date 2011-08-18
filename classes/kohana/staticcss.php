<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * @uses JSMin
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
class Kohana_StaticCss extends StaticFile {

	/**
	 * Class instances
	 *
	 * @static
	 * @var array of StaticCss instances
	 */
	protected static $_instances = array();

	/**
	 * Class instance initiating
	 *
	 * @static
	 * @param string $type
	 * @return StaticCss
	 */
	public static function instance($type = 'default')
	{
		if ( ! is_object(Arr::get(self::$_instances, $type, NULL)))
		{
			self::$_instances[$type] = new StaticCss();
		}

		return self::$_instances[$type];
	}

	public function add($css, $condition = NULL, $place = 'docroot')
	{
		$this->_add('css', $css, $condition, $place);

		return $this;
	}

	/**
	 * Minifies css file content
	 *
	 * @param  string $v
	 * @return string
	 */
	protected function minify($v)
	{
		$v       = trim($v);
		$v       = str_replace("\r\n", "\n", $v);
		$search  = array("/\/\*[\d\D]*?\*\/|\t+/", "/\s+/", "/\}\s+/");
		$replace = array(null, " ", "}\n");
		$v       = preg_replace($search, $replace, $v);
		$search  = array("/\\;\s/", "/\s+\{\\s+/", "/\\:\s+\\#/", "/,\s+/i", "/\\:\s+\\\'/i", "/\\:\s+([0-9]+|[A-F]+)/i");
		$replace = array(";", "{", ":#", ",", ":\'", ":$1");
		$v       = preg_replace($search, $replace, $v);
		$v       = str_replace("\n", null, $v);

		return $v;
	}

	public function get($place = 'docroot')
	{
		$benchmark = Profiler::start(__CLASS__, __FUNCTION__);

		if ( ! isset($this->_css[$place]) OR ! count($this->_css[$place]))
		{
			Profiler::stop($benchmark);
			return NULL;
		}

		$css_code = '';
		if($place == 'inline')
		{
			$css_inline = implode("\n", $this->_css_inline);

			if ($this->_config->css['min'])
			{
				$css_inline = $this->minify($css_inline);
			}

			foreach ($this->_css['inline'] as $condition => $css_array)
			{
				$css_inline = implode("\n", $css_array);
				if ($this->_config->css['min'])
				{
					$css_inline = $this->minify($css_inline);
				}
				$css_code .= $css_inline;
			}

			$css_code = $this->_prepare($css_code, 'css');

			if ( ! $this->_config->css['build'])
				return '<style type="text/css">' . trim($css_code) . '</style>';
		}

		// Not need to build one js file
		if ( ! $this->_config->css['build'])
		{
			foreach($this->_css[$place] as $condition => $css_array)
			{
				foreach($css_array as $css)
				{
					$css_code .= $this->_get_link('css', $css, $condition) . "\n";
				}
			}
		}
		else
		{
			if($place === 'inline')
			{
				$build_name = $this->_make_file_name($this->_css['inline'], 'inline', 'css');

				if ( ! file_exists($this->cache_file($build_name)))
				{
					$this->save($this->cache_file($build_name), $css_code);
				}

				$css_code = $this->_get_link('css', $this->cache_url($build_name));
			}
			else
			{
				$build = array();
				$css_code = '';
				foreach ($this->_css[$place] as $condition => $css_array)
				{
					foreach($css_array as $css)
					{
						$build[$condition][] = $css;
					}
				}

				foreach ($build as $condition => $css)
				{
					$build_name = $this->_make_file_name($css, $condition, 'css');

					// Checking Cache file TTL
					$this->_cache_ttl_check($build_name);

					if ( ! file_exists($this->cache_file($build_name)))
					{
						$build_content = '';
						if($place === 'inline')
						{
							$build_content .= $css_code;
						}
						else
						{
							// first time building
							foreach ($css as $url)
							{
								$_css = $this->get_source($url, $place);
								$_css = $this->_prepare($_css, 'css');

								// look if file name has 'min' suffix to avoid extra minification
								if ($this->_config->css['min'] AND
								    (! mb_strpos($url, '.min.') AND
								     ! mb_strpos($url, '.pack.') AND
								     ! mb_strpos($url, '.packed.')))
								{
									$_css = $this->minify($_css);
								}

								$build_content .= $_css;
							}
						}

						$this->save($this->cache_file($build_name), $build_content);
					}

					$css_code .= $this->_get_link('css', $this->cache_url($build_name), $condition);
				}
			}
		}

		Profiler::stop($benchmark);
		return $css_code;
	}

	/**
	 * Loads library from CDN
	 *
	 * @return null|string
	 */
	public function load_library()
	{
		$anchors = NULL;

		foreach(Arr::get($this->_css, 'cdn', array()) as $href)
		{
			$anchors = HTML::script($href);
		}

		return $anchors;
	}

} // End Kohana_StaticCss