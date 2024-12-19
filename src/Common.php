<?php defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('is_php'))
{

	function is_php($version)
	{
		static $_is_php;
		$version = (string) $version;

		if ( ! isset($_is_php[$version]))
		{
			$_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
		}

		return $_is_php[$version];
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('is_really_writable'))
{

	function is_really_writable($file)
	{
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR === '/' && (is_php('5.4') OR ! ini_get('safe_mode')))
		{
			return is_writable($file);
		}

		/* For Windows servers and safe_mode "on" installations we'll actually
		 * write a file then read it. Bah...
		 */
		if (is_dir($file))
		{
			$file = rtrim($file, '/').'/'.md5(mt_rand());
			if (($fp = @fopen($file, 'ab')) === FALSE)
			{
				return FALSE;
			}

			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);
			return TRUE;
		}
		elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
		{
			return FALSE;
		}

		fclose($fp);
		return TRUE;
	}
}

if ( ! function_exists('find_class_file'))
{
	function &find_class_file($class, $directory = 'libraries', $param = NULL){

	$name = FALSE;
	$prefix = 'CI_';

	// Cari class di folder lokal application atau sistem
	foreach ([APPPATH] as $basePath) {
		 $is_system = false;
			$filePath = resolve_path($basePath, $directory). '/' . $class . '.php';
			if (file_exists($filePath)) {
				 require_once $filePath;
				 $name = $prefix.$class;
					// Resolusi namespace untuk class
					$name_namespace = resolve_namespace(ucwords($directory), $prefix . $class, $is_system);
					$name_legacy = resolve_namespace('Legacy\\'.ucwords($directory), $prefix . $class, $is_system);
					if (class_exists($name_legacy, FALSE)) {
						$name = $name_legacy;
					} else if (class_exists($name_namespace, FALSE)) {
						$name = $name_namespace;
					}

					break;
			}
	}


	// Resolusi path ke direktori
	$directory_path = resolve_path(APPPATH, $directory);

	// Cek apakah ada extension class
	$subclass_prefix = config_item('subclass_prefix');
	$filename = $class;
	if(strpos($class,'\\')){
		$filename = basename(str_replace('\\','/',$class));
	}
	$extensionPath = $directory_path . '/' . $subclass_prefix . $filename . '.php';

	if (file_exists($extensionPath)) {
			$name = $subclass_prefix . $class;
			if (!class_exists($name, FALSE)) {
					require_once $extensionPath;
			}

			return $name;
	}


	return $name;
}
}
// ------------------------------------------------------------------------

if ( ! function_exists('load_class'))
{
	 function &load_class($class, $directory = 'libraries', $param = NULL)
	 {
	     static $_classes = array();

	     // Jika class sudah dimuat, kembalikan referensi class tersebut
	     if (isset($_classes[$class])) {
	         return $_classes[$class];
	     }



			 if(strpos($class,'\\')){
				 $_classes[$class] = loadClass($class, $class, $param);
				 return $_classes[$class];
			 }


			 $name_namespace = 'Flame\\'.ucwords(str_replace('/','\\',$directory)).'\\'.$class;
			 $name_subnamespace = $name_namespace.'\\'.$class;
			 // Is class extension in core?
			if (class_exists($name_namespace) === TRUE){
				$_classes[$class] = loadClass($class, $name_namespace, $param);
 	     return $_classes[$class];
			}

			 // Is class extension in core?
	 		if (class_exists($name_subnamespace) === TRUE){
				$_classes[$class] = loadClass($class, $name_subnamespace, $param);
 	     return $_classes[$class];
			}


			// Cari class di folder lokal application atau sistem
		 $filename = find_class_file($class, $directory, $param);
		 if($filename) {
			 $name = $filename;
		 }

	     // Jika class tidak ditemukan, tampilkan error
	     if ($name === FALSE) {
	         set_status_header(503);
	         echo 'Unable to locate the specified class: ' . $class . '.php';
	         exit(5); // EXIT_UNK_CLASS
	     }

			 $_classes[$class] = loadClass($class, $name, $param);
	     return $_classes[$class];
	 }

}


function loadClass($class, $name, $param) {

	if (class_exists($name) === FALSE){
		return FALSE;
	}
	// Tandai class sudah dimuat
	is_loaded($class);

	// Instansiasi class yang dimuat
	return isset($param)
			? new $name($param)
			: new $name();
}


// --------------------------------------------------------------------

if ( ! function_exists('is_loaded'))
{

	function &is_loaded($class = '')
	{
		static $_is_loaded = array();

		if(strpos($class,'\\')) {
			$namespace_prefix = substr($class, 0, strrpos($class, '\\'));
			$alias = trim(str_replace($namespace_prefix,'',$class),'\\');
			$_is_loaded[strtolower($alias)] = $class;
			return $_is_loaded;
		}


		if ($class !== '')
		{
			$_is_loaded[strtolower($class)] = $class;
		}

		return $_is_loaded;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('get_config'))
{
	function &get_config(Array $replace = array())
	{
		static $config;

		if (empty($config))
		{
			$file_path = resolve_path(APPPATH,'config').'/config.php';
			$found = FALSE;
			if (file_exists($file_path))
			{
				$found = TRUE;
				require($file_path);
			}

			// Is the config file in the environment folder?
			if (file_exists($file_path = resolve_path(APPPATH,'config').'/'.ENVIRONMENT.'/config.php'))
			{
				require($file_path);
			}
			elseif ( ! $found)
			{
				set_status_header(503);
				echo 'The configuration file does not exist.';
				exit(3); // EXIT_CONFIG
			}

			// Does the $config array exist in the file?
			if ( ! isset($config) OR ! is_array($config))
			{
				set_status_header(503);
				echo 'Your config file does not appear to be formatted correctly.';
				exit(3); // EXIT_CONFIG
			}
		}

		// Are any values being dynamically added or replaced?
		foreach ($replace as $key => $val)
		{
			$config[$key] = $val;
		}

		return $config;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('config_item'))
{
	function config_item($item)
	{
		static $_config;

		if (empty($_config))
		{
			// references cannot be directly assigned to static variables, so we use an array
			$_config[0] =& get_config();
		}

		return isset($_config[0][$item]) ? $_config[0][$item] : NULL;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('get_mimes'))
{
	function &get_mimes()
	{
		static $_mimes;

		if (empty($_mimes))
		{
			$_mimes = file_exists(APPPATH.'config/mimes.php')
				? include(APPPATH.'config/mimes.php')
				: array();

			if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/mimes.php'))
			{
				$_mimes = array_merge($_mimes, include(APPPATH.'config/'.ENVIRONMENT.'/mimes.php'));
			}
		}

		return $_mimes;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('is_https'))
{
	function is_https()
	{
		if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
		{
			return TRUE;
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
		{
			return TRUE;
		}
		elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
		{
			return TRUE;
		}

		return FALSE;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('is_cli'))
{

	function is_cli()
	{
		return (PHP_SAPI === 'cli' OR defined('STDIN'));
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('show_error'))
{
	function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered')
	{
		$status_code = abs($status_code);
		if ($status_code < 100)
		{
			$exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
			$status_code = 500;
		}
		else
		{
			$exit_status = 1; // EXIT_ERROR
		}

		$_error =& load_class('Exceptions', 'core');
		echo $_error->show_error($heading, $message, 'error_general', $status_code);
		exit($exit_status);
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('show_404'))
{
	function show_404($page = '', $log_error = TRUE)
	{
		$_error =& load_class('Exceptions', 'core');
		$_error->show_404($page, $log_error);
		exit(4); // EXIT_UNKNOWN_FILE
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('log_message'))
{
	function log_message($level, $message)
	{
		static $_log;

		if ($_log === NULL)
		{

			// references cannot be directly assigned to static variables, so we use an array
			$_log[0] =& load_class('Log', 'core');
		}

		$_log[0]->write_log($level, $message);
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('set_status_header'))
{
	function set_status_header($code = 200, $text = '')
	{
		if (is_cli())
		{
			return;
		}

		if (empty($code) OR ! is_numeric($code))
		{
			show_error('Status codes must be numeric', 500);
		}

		if (empty($text))
		{
			is_int($code) OR $code = (int) $code;
			$stati = array(
				100	=> 'Continue',
				101	=> 'Switching Protocols',

				200	=> 'OK',
				201	=> 'Created',
				202	=> 'Accepted',
				203	=> 'Non-Authoritative Information',
				204	=> 'No Content',
				205	=> 'Reset Content',
				206	=> 'Partial Content',

				300	=> 'Multiple Choices',
				301	=> 'Moved Permanently',
				302	=> 'Found',
				303	=> 'See Other',
				304	=> 'Not Modified',
				305	=> 'Use Proxy',
				307	=> 'Temporary Redirect',

				400	=> 'Bad Request',
				401	=> 'Unauthorized',
				402	=> 'Payment Required',
				403	=> 'Forbidden',
				404	=> 'Not Found',
				405	=> 'Method Not Allowed',
				406	=> 'Not Acceptable',
				407	=> 'Proxy Authentication Required',
				408	=> 'Request Timeout',
				409	=> 'Conflict',
				410	=> 'Gone',
				411	=> 'Length Required',
				412	=> 'Precondition Failed',
				413	=> 'Request Entity Too Large',
				414	=> 'Request-URI Too Long',
				415	=> 'Unsupported Media Type',
				416	=> 'Requested Range Not Satisfiable',
				417	=> 'Expectation Failed',
				422	=> 'Unprocessable Entity',
				426	=> 'Upgrade Required',
				428	=> 'Precondition Required',
				429	=> 'Too Many Requests',
				431	=> 'Request Header Fields Too Large',

				500	=> 'Internal Server Error',
				501	=> 'Not Implemented',
				502	=> 'Bad Gateway',
				503	=> 'Service Unavailable',
				504	=> 'Gateway Timeout',
				505	=> 'HTTP Version Not Supported',
				511	=> 'Network Authentication Required',
			);

			if (isset($stati[$code]))
			{
				$text = $stati[$code];
			}
			else
			{
				show_error('No status text available. Please check your status code number or supply your own message text.', 500);
			}
		}

		if (strpos(PHP_SAPI, 'cgi') === 0)
		{
			header('Status: '.$code.' '.$text, TRUE);
			return;
		}

		$server_protocol = (isset($_SERVER['SERVER_PROTOCOL']) && in_array($_SERVER['SERVER_PROTOCOL'], array('HTTP/1.0', 'HTTP/1.1', 'HTTP/2'), TRUE))
			? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		header($server_protocol.' '.$code.' '.$text, TRUE, $code);
	}
}

// --------------------------------------------------------------------

if ( ! function_exists('_error_handler'))
{
	function _error_handler($severity, $message, $filepath, $line)
	{
		$is_error = (((E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

		if ($is_error)
		{
			set_status_header(500);
		}

		if (($severity & error_reporting()) !== $severity)
		{
			return;
		}

		$_error =& load_class('Exceptions', 'core');
		$_error->log_exception($severity, $message, $filepath, $line);

		// Should we display the error?
		if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
		{
			$_error->show_php_error($severity, $message, $filepath, $line);
		}

		if ($is_error)
		{
			exit(1); // EXIT_ERROR
		}
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('_exception_handler'))
{
	function _exception_handler($exception)
	{
		$_error =& load_class('Exceptions', 'core');
		$_error->log_exception('error', 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine());

		is_cli() OR set_status_header(500);
		// Should we display the error?
		if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
		{
			$_error->show_exception($exception);
		}

		exit(1); // EXIT_ERROR
	}
}

function show_exception(\Exception $e, $status_code = 500)
{
	$_error =& load_class('Exceptions', 'core');
	echo $_error->show_exception($e, $status_code);
	exit;
}

// ------------------------------------------------------------------------

if ( ! function_exists('_shutdown_handler'))
{
	function _shutdown_handler()
	{
		$last_error = error_get_last();
		if (isset($last_error) &&
			($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING)))
		{
			_error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
		}
	}
}

// --------------------------------------------------------------------

if ( ! function_exists('remove_invisible_characters'))
{
	function remove_invisible_characters($str, $url_encoded = TRUE)
	{
		$non_displayables = array();

		if ($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/i';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/i';	// url encoded 16-31
			$non_displayables[] = '/%7f/i';	// url encoded 127
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('html_escape'))
{
	function html_escape($var, $double_encode = TRUE)
	{
		if (empty($var))
		{
			return $var;
		}

		if (is_array($var))
		{
			foreach (array_keys($var) as $key)
			{
				$var[$key] = html_escape($var[$key], $double_encode);
			}

			return $var;
		}

		return htmlspecialchars($var, ENT_QUOTES, config_item('charset'), $double_encode);
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('_stringify_attributes'))
{
	function _stringify_attributes($attributes, $js = FALSE)
	{
		$atts = NULL;

		if (empty($attributes))
		{
			return $atts;
		}

		if (is_string($attributes))
		{
			return ' '.$attributes;
		}

		$attributes = (array) $attributes;

		foreach ($attributes as $key => $val)
		{
			$atts .= ($js) ? $key.'='.$val.',' : ' '.$key.'="'.$val.'"';
		}

		return rtrim($atts, ',');
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('function_usable'))
{
	function function_usable($function_name)
	{
		static $_suhosin_func_blacklist;

		if (function_exists($function_name))
		{
			if ( ! isset($_suhosin_func_blacklist))
			{
				$_suhosin_func_blacklist = extension_loaded('suhosin')
					? explode(',', trim(ini_get('suhosin.executor.func.blacklist')))
					: array();
			}

			return ! in_array($function_name, $_suhosin_func_blacklist, TRUE);
		}

		return FALSE;
	}
}


if (!function_exists('make_namespace')) {
    function make_namespace(string ...$namespaces): string
    {
        return '\\' . implode('\\', array_filter($namespaces));
    }
}

if (!function_exists('resolve_namespace')) {
    function resolve_namespace(string $sub_namespace, string $class, bool $system = false, bool $resolve = true): string
    {
        if (!$resolve) {
            return $class; // Return only the class name if resolving is disabled.
        }

        // Determine the root namespace based on the system flag.
        $root_namespace = $system ? 'Flame' : APP_NAMESPACE;

        // Build the fully qualified namespace.
        return make_namespace($root_namespace, $sub_namespace, $class);
    }
}

function load_controller_with_dependencies($controllerClass)
{
  // Gunakan refleksi untuk mendapatkan parameter di konstruktor
  $reflector = new ReflectionClass($controllerClass);
	// Ambil konstruktor
	$constructor = $reflector->getConstructor();

  if (!$constructor) {
    return new $controllerClass;
    // Jika tidak ada konstruktor, kembalikan instance langsung
  }
  // Ambil parameter dari konstruktor
  $parameters = $constructor->getParameters();
  $dependencies = [];
  foreach ($parameters as $parameter) {
    // Ambil tipe parameter
    $type = $parameter->getType();
    if ($type && !$type->isBuiltin()) {
      $className = $type->getName();
      // Resolusi dependency (DI)
      if (class_exists($className)) {
        $dependencies[] = load_dependency($className);
      } else {
        throw new Exception("Class {$className} tidak ditemukan untuk parameter {$parameter->getName()}.");
      }
    }
  }


  // Buat instance controller dengan dependensi yang di-*resolve*
  return $reflector->newInstanceArgs($dependencies);
}



function load_dependency($className)
{
  $CI =& get_instance();
  // Pastikan model atau library dimuat jika dibutuhkan
/*  if ($CI->load->is_loaded($className)) {
    return $CI->{$className};
  }

  // Muat secara manual jika belum ada
  if (strpos($className, 'Model') !== false) {
    $CI->load->model($className);
    return $CI->{$className};
  } elseif (strpos($className, 'Service') !== false) {
    $CI->load->library($className);
    // Atur library Anda
    return new $className();
  }
*/
  // Kembalikan instance langsung jika kelas ada
  //return new $className();
	return load_controller_with_dependencies($className);

}
