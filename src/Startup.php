<?php

if (!function_exists('resolve_path')) {

    function resolve_path(string $basePath = '', string $directory = ''): string {
        $basePath = rtrim($basePath, '/') . '/';

        // Check lowercase directory first, then uppercase.
        $uppercasePath = $basePath . ucwords($directory);
        $lowercasePath = $basePath . strtolower($directory);
        $ucfirstcasePath = $basePath . ucfirst(strtolower($directory));

        if (is_dir($lowercasePath)) {
            return $lowercasePath;
        } elseif (is_dir($ucfirstcasePath)) {
            return $ucfirstcasePath;
        }

        // Return the uppercase directory path as default if neither exists.
        return $uppercasePath;
    }
}


defined('BASEPATH') || define('BASEPATH', __DIR__.DIRECTORY_SEPARATOR);
defined('STORAGEPATH') || define('STORAGEPATH', FCPATH.'storage'.DIRECTORY_SEPARATOR);
define('CI_VERSION', '3.1.11');

$appSetup = file_exists(APPPATH.'app.setup.php') ? include(APPPATH.'app.setup.php') : ['namespace'=>'App'];

define('APP_NAMESPACE', $appSetup['namespace']);

if (file_exists(resolve_path(APPPATH,'config').'/'.ENVIRONMENT.'/constants.php'))
{
	require_once(resolve_path(APPPATH,'config').'/'.ENVIRONMENT.'/constants.php');
}

if (file_exists(resolve_path(APPPATH,'config').'/constants.php'))
{
	require_once(resolve_path(APPPATH,'config').'/constants.php');
}


/*
 * ------------------------------------------------------
 *  Load the autoloader and register it
 * ------------------------------------------------------
 */
 if(!file_exists(BASEPATH.'Core/Engine/Autoloader.php')) {
   header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
   echo 'Your Core/Autoloader.php file does not appear to be set correctly.';
   exit(3); // EXIT_CONFIG
 } else {
  class_exists('Flame\Core\Engine\Autoloader') OR require BASEPATH.'Core/Engine/Autoloader.php';
 	Flame\Core\Engine\Autoloader::getInstance()->register();
 }

/*
 * ------------------------------------------------------
 *  Load the global functions
 * ------------------------------------------------------
 */
	require __DIR__.'/Common.php';


	/*
	 * ------------------------------------------------------
	 * Security procedures
	 * ------------------------------------------------------
	 */

	if ( ! is_php('5.4'))
	{
		ini_set('magic_quotes_runtime', 0);

		if ((bool) ini_get('register_globals'))
		{
			$_protected = array(
				'_SERVER',
				'_GET',
				'_POST',
				'_FILES',
				'_REQUEST',
				'_SESSION',
				'_ENV',
				'_COOKIE',
				'GLOBALS',
				'HTTP_RAW_POST_DATA',
				'system_path',
				'application_folder',
				'view_folder',
				'_protected',
				'_registered'
			);

			$_registered = ini_get('variables_order');
			foreach (array('E' => '_ENV', 'G' => '_GET', 'P' => '_POST', 'C' => '_COOKIE', 'S' => '_SERVER') as $key => $superglobal)
			{
				if (strpos($_registered, $key) === FALSE)
				{
					continue;
				}

				foreach (array_keys($$superglobal) as $var)
				{
					if (isset($GLOBALS[$var]) && ! in_array($var, $_protected, TRUE))
					{
						$GLOBALS[$var] = NULL;
					}
				}
			}
		}
	}


	/*
	 * ------------------------------------------------------
	 *  Define a custom error handler so we can log PHP errors
	 * ------------------------------------------------------
	 */
	//set_error_handler('_error_handler');
	//set_exception_handler('_exception_handler');
	//register_shutdown_function('_shutdown_handler');

/*
 * ------------------------------------------------------
 *  Check for the installer if we're booting the CP
 * ------------------------------------------------------
 */

 $framework = new \Flame\Core\Engine\Framework(new \Flame\Core\Dependency\DependencyResolver());
 //$framework = new \Flame\Core\Engine\Framework();

	$charset = strtoupper(config_item('charset'));
	ini_set('default_charset', $charset);

	if (extension_loaded('mbstring'))
	{
		define('MB_ENABLED', TRUE);
		@ini_set('mbstring.internal_encoding', $charset);
		mb_substitute_character('none');
	}
	else
	{
		define('MB_ENABLED', FALSE);
	}

	if (extension_loaded('iconv'))
	{
		define('ICONV_ENABLED', TRUE);
		@ini_set('iconv.internal_encoding', $charset);
	}
	else
	{
		define('ICONV_ENABLED', FALSE);
	}

	if (is_php('5.6'))
	{
		ini_set('php.internal_encoding', $charset);
	}


	require_once(BASEPATH.'Core/Compat/mbstring.php');
	require_once(BASEPATH.'Core/Compat/hash.php');
	require_once(BASEPATH.'Core/Compat/password.php');
	require_once(BASEPATH.'Core/Compat/standard.php');

/*
 * ------------------------------------------------------
 *  Boot the core
 * ------------------------------------------------------
 */
	$framework->boot();

/*
 * ------------------------------------------------------
 *  Set config items from the index.php file
 * ------------------------------------------------------
 */
	if (isset($assign_to_config))
	{
		$framework->overrideConfig($assign_to_config);
	}

/*
 * ------------------------------------------------------
 *  Set routing overrides from the index.php file
 * ------------------------------------------------------
 */
	if (isset($routing))
	{
		$framework->overrideRouting($routing);
	}

/*
 * ------------------------------------------------------
 *  Create global helper functions
 *
 *  Using `CI` for the global name, just in case someone
 *  is relying on that instead of get_instance()
 * ------------------------------------------------------
 */

	function &flame($dep = NULL)
	{
		return Flame\Controller::get_instance($dep);
	}

  function &get_instance() {

    return flame();
  }

/*
 * ------------------------------------------------------
 *  Parse the request
 * ------------------------------------------------------
 */
	$request = \Flame\Core\Engine\Http\Request::fromGlobals();

/*
 * ------------------------------------------------------
 *  Run the request and get a response
 * ------------------------------------------------------
 */

	$response = $framework->run($request);

/*
 * ------------------------------------------------------
 *  Send the response
 * ------------------------------------------------------
 */
	if ($response)
	{
		$response->send();
	}

// EOF
