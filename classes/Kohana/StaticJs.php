<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Treating javascript files and script strings
 *
 * @todo    make TTL checking of build files
 * @uses    JSMin
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
class Kohana_StaticJs extends StaticFile
{

    /**
     * Class instances
     *
     * @static
     * @var array of StaticJs instances
     */
    protected static $_instances = [];

    /**
     * Class instance initiating
     *
     * @static
     *
     * @param string $type
     *
     * @return StaticJs
     */
    public static function instance($type = 'default')
    {
        if ( ! is_object(Arr::get(self::$_instances, $type, null))) {
            self::$_instances[$type] = new StaticJs();
        }

        return self::$_instances[$type];
    }

    /**
     * Adding script from a docroot
     *
     * @chainable
     *
     * @param  string      $href
     * @param  string|null $condition
     *
     * @return StaticJs
     */
    public function add($href, $condition = null)
    {
        $this->_add_as_docroot('js', $href, $condition);

        return $this;
    }

    /**
     * Adding inline script
     *
     * @chainable
     *
     * @param  string      $js
     * @param  string|null $condition
     * @param  string|null $id
     *
     * @return StaticJs
     */
    public function add_inline($js, $condition = null, $id = null)
    {
        $this->_add_as_inline('js', $js, $condition, $id);

        return $this;
    }

    /**
     * Adding script from a modules path (media folder in the module)
     *
     * @chainable
     *
     * @param  string      $href
     * @param  string|null $condition
     *
     * @return StaticJs
     */
    public function add_modpath($href, $condition = null)
    {
        $this->_add_as_modpath('js', $href, $condition);

        return $this;
    }

    /**
     * Adding script from a CDN
     *
     * @chainable
     *
     * @param  string      $href
     * @param  string|null $condition
     *
     * @return StaticJs
     */
    public function add_cdn($href, $condition = null)
    {
        $this->_add_as_cdn('js', $href, $condition);

        return $this;
    }

    /**
     * Getting all scripts that was added earlier
     *
     * @return null|string
     */
    public function get_all()
    {
        $benchmark = $this->_start_benchmark('js');
        $js_links  = null;
        $inline_js = null;
        $build     = [];

        foreach ($this->_js as $condition => $js_arr) {
            $js_arr = Arr::flatten($js_arr);
            foreach ($js_arr as $resource => $destination) {
                switch ($destination) {
                    case 'inline':
                        $inline_js .= $resource;
                        break;
                    case 'docroot':
                    case 'modpath':
                    case 'cdn':
                        if ($destination == 'modpath') {
                            $this->_move_to_docroot_cache($resource);
                            $resource = $this->_config->temp_docroot_path . $resource;
                        }

                        if ( ! $this->_config->js['build']) {
                            $js_links .= $this->_get_link('js', $resource, $condition) . "\n";
                        } else {
                            $build[$condition][] = $resource;
                        }

                        break;
                }
            }
        }

        // If one file building of inline scripts is needed
        if ($this->_config->js['build'] AND $inline_js) {
            $inline_js = JSMin::minify($inline_js);
        }

        $inline_js = $this->_prepare($inline_js, 'js');

        if ( ! $this->_config->js['build']) {
            $js_links .= '<script language="JavaScript" type="text/javascript">' . trim($inline_js) . "</script>\n";
        }

        foreach ($build as $condition => $js_link_arr) {
            $build_content = '';
            $build_name    = $this->_make_file_name($js_link_arr, $condition, 'js');

            // Checking Cache file TTL
//				$this->_cache_ttl_check($build_name);

            if ( ! file_exists($this->cache_file($build_name))) {
                // first time building
                foreach ($js_link_arr as $url) {
                    $_js = $this->get_source($url);

                    // look if file name has 'min' suffix to avoid extra minification
                    if ($this->_config->js['min'] AND ( ! mb_strpos($url, '.min.') AND ! mb_strpos($url, '.pack.')
                            AND ! mb_strpos($url, '.packed.'))
                    ) {
                        $_js = JSMin::minify($_js);
                    }

                    $build_content .= $_js;
                }

                $this->save($this->cache_file($build_name), $build_content);
            }

            $js_links .= $this->_get_link('js', $this->cache_url($build_name), $condition);
        }

        // If one file building of inline scripts is needed
        if ($inline_js AND $this->_config->js['build']) {
            $build_name = $this->_make_file_name($inline_js, 'inline', 'js');
            if ( ! file_exists($this->cache_file($build_name))) {
                $this->save($this->cache_file($build_name), $inline_js);
            }

            $js_links .= $this->_get_link('js', $this->cache_url($build_name));
        }

        if ($benchmark) {
            Profiler::stop($benchmark);
        }

        self::$_count++;

        return $js_links;
    }
    /**
     * @param      $href
     * @param null $condition
     *
     * @return $this
     */
    public function remove($href, $condition = '')
    {
        return $this->_removePath('_js', $href, $condition);
    }

} // End Kohana_StaticJs