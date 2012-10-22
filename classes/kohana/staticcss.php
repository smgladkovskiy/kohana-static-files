<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Treating styles files and inline strings
 *
 * @todo    make TTL checking of build files
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
	protected static $_instances    = array();

	/**
	 * Array of merged and de-"@import"ed css
	 *
	 * @var array
	 */
	protected        $_merged_css = array();

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

	/**
	 * Adding script from a docroot
	 *
	 * @chainable
	 * @param  string      $href
	 * @param  string|null $condition
	 * @return StaticJs
	 */
	public function add($href, $condition = NULL)
	{
		$this->_add_as_docroot('css', $href, $condition);
		return $this;
	}

	/**
	 * Overloaded version for recursive getting "@import" files
	 *
	 * @param string      $type
	 * @param string      $href
	 * @param string|null $condition
	 * @return void
	 */
	public function _add_as_docroot($type, $href, $condition = NULL) {
		if ($this->_config->css['build'])
		{
			// Merge @import'ed css in one file. Recursively.
			$file_path = DOCROOT.$href;
			if (is_readable($file_path))
			{
				$v = file_get_contents($file_path);
			}
			else
			{
				throw new Kohana_Exception('Unable to read file: :file', array(':file' => $file_path));
			}

			/**
			 * @todo: Add check for in-comment place
			 */
			$pattern = '/\@import\s+(?:url\s*\()?\s*["\']{1}([a-zA-Z0-9'."\\\\\/".']+\.css)["\']{1}\s*\)?\s*;/';
			$matches = array();
			$dir     = DOCROOT.dirname($href).DIRECTORY_SEPARATOR;
			$i       = 0;

			while (preg_match($pattern, $v))
			{
				preg_match_all($pattern, $v, $matches);
				if ( ! empty($matches[1]))
				{
					foreach($matches[1] as $css)
					{
						if ($i>20)
						{
							throw new Kohana_Exception('You have more than 20 levels in css @import files or recursive definition. Check your css files.');
						}
						elseif (is_readable($dir.$css))
						{
							$replace = file_get_contents($dir.$css);
							$v = preg_replace('/\@import[^;]+'.$css.'[^;]+;/', $replace, $v);
						}
						else
						{
							throw new Kohana_Exception('Can\'t read css file: :css_filename.', array(':css_filename' => $dir.$css), Kohana_Exception::$php_errors[E_ERROR]);
						}
					}
				}
				$i++;
			}
			$this->_merged_css[$href] = $v;
		}

		parent::_add_as_docroot($type, $href, $condition);
	}

	/**
	 * Adding inline script
	 *
	 * @chainable
	 * @param  string      $js
	 * @param  string|null $condition
	 * @param  string|null $id
	 * @return StaticJs
	 */
	public function add_inline($js, $condition = NULL, $id = NULL)
	{
		$this->_add_as_inline('css', $js, $condition, $id);
		return $this;
	}

	/**
	 * Adding script from a modules path (media folder in the module)
	 *
	 * @chainable
	 * @param  string      $href
	 * @param  string|null $condition
	 * @return StaticJs
	 */
	public function add_modpath($href, $condition = NULL)
	{
		$this->_add_as_modpath('css', $href, $condition);
		return $this;
	}

	/**
	 * Adding script from a CDN
	 *
	 * @chainable
	 * @param  string      $href
	 * @param  string|null $condition
	 * @return StaticJs
	 */
	public function add_cdn($href, $condition = NULL)
	{
		$this->_add_as_cdn('css', $href, $condition);
		return $this;
	}

	/**
	 * Getting all styleshits that was added earlier
	 *
	 * @return null|string
	 */
	public function get_all()
	{
		$benchmark = $this->_start_benchmark('css');
		$css_links = NULL;
		$inline_css = NULL;
		$build = array();

		foreach($this->_css as $condition => $css_arr)
		{
			$css_arr = Arr::flatten($css_arr);
			foreach($css_arr as $resource => $destination)
			{
				switch($destination)
				{
					case 'inline':
						$inline_css .= $resource;
						break;
					case 'docroot':
					case 'modpath':
					case 'cdn':
						if($destination == 'modpath')
						{
							$this->_move_to_docroot_cache($resource);
							$resource = $this->_config->temp_docroot_path.$resource;
						}

						if ( ! $this->_config->css['build'])
						{
							$css_links .= $this->_get_link('css', $resource, $condition) . "\n";
						}
						else
						{
							$build[$condition][] = $resource;
						}

						break;
				}
			}
		}

		// If one file building of inline scripts is needed
		if($this->_config->js['build'] AND $inline_css)
		{
			$inline_css =  $this->minify($inline_css);
		}

		$inline_css = $this->_prepare($inline_css, 'css');

		if ( ! $this->_config->css['build'])
		{
			$css_links .= '<style type="text/css">' . trim($inline_css) . '</style>';
		}

		foreach ($build as $condition => $css_link_arr)
		{
			$build_content = '';
			$build_name = $this->_make_file_name($css_link_arr, $condition, 'css');

			// Checking Cache file TTL
//				$this->_cache_ttl_check($build_name);

			if ( ! file_exists($this->cache_file($build_name)))
			{
				// first time building
				foreach ($css_link_arr as $url)
				{
					$_css = $this->_merged_css[$url];
					if (empty($_css))
					{
						$_css = $this->get_source($url);
					}
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

				$this->save($this->cache_file($build_name), $build_content);
			}
			$css_links .= $this->_get_link('css', $this->cache_url($build_name), $condition);
		}

		// If one file building of inline scripts is needed
		if($inline_css AND $this->_config->css['build'])
		{
			$build_name = $this->_make_file_name($inline_css, 'inline', 'css');
			if ( ! file_exists($this->cache_file($build_name)))
			{
				$this->save($this->cache_file($build_name), $inline_css);
			}

			$css_links .= $this->_get_link('css', $this->cache_url($build_name));
		}
		Profiler::stop($benchmark);
		return $css_links;
	}

} // End Kohana_StaticCss