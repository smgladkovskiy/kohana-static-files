# How To Use

## To add style or javascript in any place (Controller or View):

* Adding real existing files of styles on server or other host

        StaticCss::instance()->add('css/admin.css');

* The same but with browser condition

        // <!--[lte IE 7]><link rel="stylesheet" href="/css/quickform.css" media="all" type="text/css" /><![endif]-->
        StaticCss::instance()->addCss('css/quickform.css', 'lte IE 7');

* Adding virtual stylesheet file (will be searching in APPPATH.'static-files'.$file and MODPATH.$module.'static-files'.$file)

        StaticCss::instance()->add('style.css', 'modpath');

* Inline styles adding

        StaticCss::instance()->add('.a:hover{color:red}', 'inline');

* Adding real existing files of scripts on server or other host

        StaticJs::instance()->addJs('js/pirobox.js');

* Adding virtual javascript file

        StaticJs::instance()->add('jquery/jquery-1.4.3.min.js', 'modpath');

* Inline scripts adding

        StaticJs::instance()->add('alert(\'test!\');', 'inline');

## To load all added javascripts or scripts

        StaticJs::instance()->get_all();
        StaticCss::instance()->get_all();