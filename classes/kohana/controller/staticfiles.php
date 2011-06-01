<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Суть контроллера: иметь возможность создания компактных модулей, в которых бы
 * можно было хранить и css, и js, и картинки выше DOCUMENT_ROOT, чтобы
 * при развертывании проекта не забывать копировать их куда надо
 * Просто бросаем модуль в modules, прописываем его в bootstrapper
 * Затем текущий контроллер, при первом же запросе
 *
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Brotkin Ivan (BIakaVeron) <BIakaVeron@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
abstract class Kohana_Controller_Staticfiles extends Controller {

	/**
	 * Static files deploying
	 *
	 * @param  string $file
	 * @return void
	 */
	public function action_index($file)
	{
		$info = pathinfo($file);

		if (($orig = self::static_original($file)))
		{
			$deploy = self::_static_deploy($file);

			//производим deploy статического файла, в следующий раз его будет
			//отдавать сразу веб-сервер без запуска PHP
			copy($orig, $deploy);

			//а пока отдадим файл руками
			$this->request->response()
				->check_cache(sha1($this->request->uri()) . filemtime($orig), $this->request)
				->body(file_get_contents($orig))
				->headers('last-modified', date('r', filemtime($orig)))
				->headers('content-type', File::mime_by_ext($info['extension']));
		}
		else
		{
			// Return a 404 status
			$this->request->response()->status(404);
		}
	}

	/**
	 * Searching original static file in static-files module directory
	 *
	 * @todo make search in different folders + exact folder, passed to a method
	 * @param  string $file
	 * @return string
	 */
	public static function static_original($file)
	{
		$info = pathinfo($file);
		$dir  = ('.' != $info['dirname']) ? $info['dirname'] . '/' : '';

		return Kohana::find_file('static-files', $dir . $info['filename'], $info['extension']);
	}

	/**
	 * Deploying static file
	 *
	 * @static
	 * @param  $file
	 * @return string
	 */
	protected static function _static_deploy($file)
	{
		$info   = pathinfo($file);
		$dir    = ('.' != $info['dirname']) ? $info['dirname'] . '/' : '';
		$deploy = Kohana::config('staticfiles.path')
		        . Kohana::config('staticfiles.url') . $dir
		        . $info['filename'] . '.'
		        . $info['extension'];

		if (!file_exists(dirname($deploy)))
			mkdir(dirname($deploy), 0777, true);

		return $deploy;
	}

} // Kohana_Controller_Staticfiles