<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Main StaticFile class
 *
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
class Kohana_StaticFile
{

    public static $_count = 0;
    /**
     * @var Kohana_Config
     */
    protected $_config;
    /**
     * Javascript links and files container
     *
     * @var array
     */
    protected $_js = [];
    /**
     * CSS links and files container
     *
     * @var array
     */
    protected $_css = [];

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_config = Kohana::$config->load('static-files');
    }

    /**
     * Blocking mechanism of file saving
     *
     * @see http://forum.dklab.ru/viewtopic.php?p=96622#96622
     *
     * @param  string $file
     * @param  string $data
     *
     * @return void
     */
    function save($file, $data)
    {
        // Creating empty file if it is not exists
        // If exists - this operation will make no harm to it
        fclose(fopen($file, "a+b"));

        // File blocking
        if ( ! ($f = fopen($file, "r+b"))) {
            throw new Kohana_Exception(__('Can\'t open cache file!'));
        }

        // Waiting a monopole owning
        flock($f, LOCK_EX);

        // Writing file
        fwrite($f, $data);

        fclose($f);
        chmod($file, 0666);
    }

    public function get_source($url)
    {
        $file_path = DOCROOT . str_replace('/', DIRECTORY_SEPARATOR, $url);

        if ($file_path) {
            $content = file_get_contents($file_path);
        }
        if ($content === false) {
            throw new Kohana_Exception('Unable to read file: :file', [':file' => $file_path]);
        }

        return $content;
    }

    /**
     * Returns cache file url
     *
     * @param  string $file_name
     *
     * @return string
     */
    public function cache_url($file_name)
    {
        return $this->_config->url . $file_name;
    }

    /**
     * Adds $type files that can be found in $type directory in DOCROOT to $type container
     *
     * @param string      $type
     * @param string      $href
     * @param string|null $condition
     *
     * @return void
     */
    protected function _add_as_docroot($type, $href, $condition = null)
    {
        $container                        = '_' . $type;
        $this->{$container}[$condition][] = [$href => 'docroot'];
    }

    /**
     * Adds $type files that can be found in $type directory in MODPATH to $type container
     *
     * @param  string      $type
     * @param  string      $href
     * @param  string|null $condition
     *
     * @return void
     */
    protected function _add_as_modpath($type, $href, $condition = null)
    {
        $container                        = '_' . $type;
        $this->{$container}[$condition][] = [$href => 'modpath'];
    }

    /**
     * Adds inline code
     *
     * @param  string      $type
     * @param  string      $code
     * @param  string|null $condition
     * @param  int|null    $id
     *
     * @return void
     */
    protected function _add_as_inline($type, $code, $condition = null, $id = null)
    {
        $container = '_' . $type;
        $uniq_id   = md5($code);

        $code = $this->_prepare($code, $type);
        if ($id !== null) {
            $this->{$container}[$condition][$uniq_id . $id] = [$code => 'inline'];
        } else {
            $this->{$container}[$condition][$uniq_id] = [$code => 'inline'];
        }
    }

    /**
     * Prepares code before deploying:
     *  - changes stubs to a STATICFILES_URL
     *  - minifying code
     *
     * @param  string $code
     * @param  string $type
     *
     * @return string
     */
    protected function _prepare($code, $type)
    {
        $code = str_replace('{staticfiles_url}', STATICFILES_URL, $code);

        if ($type === 'css') {
            if ($this->_config->css['min']) {
                $code = $this->minify($code);
            }
        }

        return trim($code);
    }

    /**
     * Minifies css file content
     *
     * @param  string $v
     *
     * @return string
     */
    protected function minify($v)
    {
        $v       = trim($v);
        $v       = str_replace("\r\n", "\n", $v);
        $search  = ["/\/\*[\d\D]*?\*\/|\t+/", "/\s+/", "/\}\s+/"];
        $replace = [null, " ", "}\n"];
        $v       = preg_replace($search, $replace, $v);
        $search  = ["/\\;\s/", "/\s+\{\\s+/", "/\\:\s+\\#/", "/,\s+/i", "/\\:\s+\\\'/i", "/\\:\s+([0-9]+|[A-F]+)/i"];
        $replace = [";", "{", ":#", ",", ":\'", ":$1"];
        $v       = preg_replace($search, $replace, $v);
        $v       = str_replace("\n", null, $v);

        return $v;
    }

    protected function _add_as_cdn($type, $href, $condition = null)
    {
        $container                        = '_' . $type;
        $this->{$container}[$condition][] = [$href => 'cdn'];
    }

    /**
     * Gets html code of the anchor
     *
     * @param  string      $type css or js
     * @param  string      $href
     * @param  string|null $condition
     *
     * @return string
     */
    protected function _get_link($type, $href, $condition = null)
    {
        $href = trim($href, '/');
        if (mb_substr($href, 0, 4) != 'http') {
            $href = ($this->_config->host == '/') ? $href : $this->_config->host . $href;
        }

        $anchor = null;
        switch ($type) {
            case 'js':
                $anchor = HTML::script($href);
                break;
            case 'css':
                $anchor = HTML::style($href, ['media' => 'all']);
                break;
        }

        return ($condition ? '<!--[if ' . $condition . ']>' : '') . $anchor . ($condition ? '<![endif]-->' : '') . "\n";
    }

    /**
     * Generates unique file name for a build file
     *
     * @param  array|string $files
     * @param  string|null  $condition_prefix
     * @param  string       $type
     *
     * @return string
     */
    protected function _make_file_name($files, $condition_prefix = null, $type)
    {
        $hash = null;
        if (is_string($files)) {
            // hash must depends on inline contend due it can be dynamic!
            // @todo: find faster and lighter encoding algorithm if it possible
            $hash = md5($files);
        } else {
            $files = (array) $files;
            $files = array_unique($files);
            $hash  = null;

            foreach ($files as $file) {
                $hash .= serialize(realpath(DOCROOT . $file));
            }
        }

        $condition_prefix = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '-', $condition_prefix));
        $condition_prefix = $condition_prefix ? ($condition_prefix . '/') : '';
        $file_name        = md5($this->_config->host . $hash);

        return $type . '/' . $condition_prefix . substr($file_name, 0, 1) . '/' . substr($file_name, 1, 1) . '/'
            . $file_name . '.' . $type;
    }

    /**
     * Clearing cache if expires its time to live
     *
     * @param  $build_name
     *
     * @return bool
     */
    protected function _cache_ttl_check($build_name)
    {
        if (file_exists($this->cache_file($build_name)) AND (filectime($this->cache_file($build_name))
                + $this->_config->cache_reset_interval) < time()
        ) {
            $this->_cache_reset();

            return false;
        }

        return true;
    }

    /**
     * Returns cache file path
     *
     * @param  string $file_name
     *
     * @return string
     */
    public function cache_file($file_name)
    {
        $cache_file = $this->_config->path . preg_replace('/\//', DIRECTORY_SEPARATOR, $this->_config->cache)
            . preg_replace('/\//', DIRECTORY_SEPARATOR, $file_name);

        if ( ! file_exists(dirname($cache_file))) {
            $umask = umask();
            umask(0);
            mkdir(dirname($cache_file), 0777, true);
            umask($umask);
        }

        return $cache_file;
    }

    /**
     * Clearing cache folders
     *
     * @return void
     */
    protected function _cache_reset()
    {
        $cache_paths = [$this->_config->cache, $this->_config->url];

        foreach ($cache_paths as $path) {
            $path = DOCROOT . trim(preg_replace('/\//', DIRECTORY_SEPARATOR, $path), '\\');
            File::rmdir($path, true);
        }
    }

    /**
     * Copy file from modpath to docroot directory to access if from the outside
     *
     * @param  string $file
     *
     * @return void
     */
    protected function _move_to_docroot_cache($file)
    {
        $docroot_tmp_path = DOCROOT . $this->_config->temp_docroot_path;

        if ( ! file_exists($docroot_tmp_path)) {
            mkdir($docroot_tmp_path, 0777, true);
        }

        $file                  = pathinfo($file);
        $file_path             =
            Kohana::find_file('media', $file['dirname'] . DIRECTORY_SEPARATOR . $file['filename'], $file['extension']);
        $docroot_tmp_path_file = $docroot_tmp_path . DIRECTORY_SEPARATOR . $file['dirname'] . DIRECTORY_SEPARATOR;
        $docroot_file_path     = $docroot_tmp_path_file . $file['basename'];

        if ( ! file_exists($docroot_tmp_path_file)) {
            mkdir($docroot_tmp_path_file, 0777, true);
        }

        if ($file_path AND ( ! file_exists($docroot_file_path) OR filectime($docroot_file_path) > filectime($file_path
                ))
        ) {
            copy($file_path, $docroot_file_path);
        }
    }

    /**
     * Benchmark initialisation
     *
     * @param  string $type
     *
     * @return null|string
     */
    protected function _start_benchmark($type)
    {
        $container = '_' . $type;
        $class     = 'StaticFiles';

        $benchmark = Profiler::start($class, 'loading ' . $type);

        if ( ! count($this->{$container}) AND $benchmark) {
            Profiler::stop($benchmark);

            return null;
        }

        return $benchmark;
    }

    protected function _removePath($container, $href, $condition = "")
    {
        if (!isset($this->{$container}[$condition])
            || empty($this->{$container}[$condition])) {
            return;
        }

        $array =& $this->{$container}[$condition];
        foreach ((array) $array as $key => $value) {
            if ($href === key($value)) {
                unset($array[$key]);
                break;
            }
        }
        unset($array);

        return $this;
    }

} // End Kohana_StaticFile