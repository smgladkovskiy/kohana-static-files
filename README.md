# How To Use

## Create (on filesystem), check permissions for web-server writting and set directory for building and holding static files in config/static-files:
	'temp_docroot_path' => 'media/static/',
	'url' => 'media/static/',
	// Path to styles and scripts builds
	'cache' => 'media/cache/',

### !Important note: building and minify optimization allowed only in production (see config file for explain)!

## To add style or javascript in any place (Controller or View):

* Adding real existing files of styles on server or other host
        StaticCss::instance()->add('css/admin.css');

* The same but with browser condition
        // <!--[lte IE 7]><link rel="stylesheet" href="/css/quickform.css" media="all" type="text/css" /><![endif]-->
        StaticCss::instance()->add('css/quickform.css', 'lte IE 7');

* Adding virtual stylesheet file (will be searching in APPPATH.'static-files'.$file and MODPATH.$module.'static-files'.$file)
        StaticCss::instance()->add('style.css', NULL, 'modpath');

* Inline styles adding
        StaticCss::instance()->add('.a:hover{color:red}', NULL, 'inline');

* Adding real existing files of scripts on server or other host
        StaticJs::instance()->add('js/pirobox.js');

* Adding virtual javascript file
        StaticJs::instance()->add('jquery/jquery-1.4.3.min.js', NULL, 'modpath');

* Inline scripts adding
        StaticJs::instance()->add('alert(\'test!\');', NULL, 'inline');

## To load all added javascripts or scripts
        StaticJs::instance()->get_all();
        StaticCss::instance()->get_all();