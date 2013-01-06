<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Static Files rendering controller
 *
 * @package Kohana-static-files
 * @author  Berdnikov Alexey <aberdnikov@gmail.com>
 * @author  Brotkin Ivan (BIakaVeron) <BIakaVeron@gmail.com>
 * @author  Sergei Gladkovskiy <smgladkovskiy@gmail.com>
 */
abstract class Kohana_Controller_Staticfiles extends Controller {

	/**
	 * Media files rendering
	 *
	 * @return void
	 */
	public function action_media()
	{
		// Generate and check the ETag for this file
		HTTP::check_cache($this->request, $this->response, sha1($this->request->uri()));

		// Get the file path from the request
		$file = $this->request->param('file');

		// Find the file extension
		$ext = pathinfo($file, PATHINFO_EXTENSION);

		// Remove the extension from the filename
		$file = substr($file, 0, -(strlen($ext) + 1));
		if($file = Kohana::find_file('media', $file, $ext)) {
			// Send the file content as the response
			$this->response->body(file_get_contents($file));
		}
		else
		{
			// Return a 404 status
			$this->response->status(404);
		}

		// Set the proper headers to allow caching
		$this->response
			->headers('Content-Type', File::mime_by_ext($ext))
			->headers('Content-Length', '' . filesize($file))
			->headers('Last-Modified', date('r', filemtime($file)))
			->send_headers();
	}

} // End Kohana_Controller_Staticfiles