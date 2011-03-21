<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Суть контроллера: иметь возможность создания компактных модулей, в которых бы
 * можно было хранить и css, и js, и картинки выше DOCUMENT_ROOT, чтобы
 * при развертывании проекта не забывать копировать их куда надо
 * Просто бросаем модуль в modules, прописываем его в bootstrapper
 * Затем текущий контроллер, при первом же запросе
 *
 * @package Kohana-static-files
 * @author Berdnikov Alexey <aberdnikov@gmail.com>
 */
abstract class Controller_Staticfiles extends Controller {

	/**
	 * Развертывание статики по мере необходимости
	 */
	public function action_index($file)
	{
		$this->auto_render  = FALSE;

		$info = pathinfo($file);

		if (($orig = self::static_original($file)))
		{
			$deploy = self::static_deploy($file);

			//производим deploy статического файла, в следующий раз его будет
			//отдавать сразу веб-сервер без запуска PHP
			copy($orig, $deploy);

			//а пока отдадим файл руками
			$this->response->check_cache(sha1($this->request->uri) . filemtime($orig));

			$this->response->headers('Content-Type', File::mime_by_ext($info['extension']));
			$this->response->headers('Content-Length', filesize($orig));
			$this->response->headers('Last-Modified', date('r', filemtime($orig)));

			$this->response->body(file_get_contents($orig));
		}
		else
		{
			// Return a 404 status
			$this->response->status(404);
		}
	}

	/**
	 * Поиск по проекту статичного файла
	 * (полный путь к файлу)
	 * @param string $file
	 * @return string
	 */
	public static function static_original($file)
	{
		$info = pathinfo($file);
		$dir  = ($info['dirname'] != '.') ? $info['dirname'] . DIRECTORY_SEPARATOR : '';

		return Kohana::find_file('static-files', $dir . $info['filename'], $info['extension']);
	}

	public static function static_deploy($file)
	{
		$info   = pathinfo($file);
		$dir    = ($info['dirname'] != '.') ? $info['dirname'] . DIRECTORY_SEPARATOR : '';
		$deploy = Kohana::config('staticfiles.path')
		        . Kohana::config('staticfiles.url') . $dir
		        . $info['filename'] . '.'
		        . $info['extension'];

		if ( ! file_exists(dirname($deploy)))
			mkdir(dirname($deploy), 0777, true);

		return $deploy;
	}
}