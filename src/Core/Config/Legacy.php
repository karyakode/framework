<?php namespace Flame\Core\Config;
defined('BASEPATH') OR exit('No direct script access allowed');

use Flame\Core\Module\Module;

class Legacy {


	public $config = array();

	public $is_loaded =	array();

	public $_config_paths =	array(APPPATH);

	public function __construct()
	{
		$this->config =& get_config();

		// Set the base_url automatically if none was provided
		if (empty($this->config['base_url']))
		{
			if (isset($_SERVER['SERVER_ADDR']))
			{
				if (strpos($_SERVER['SERVER_ADDR'], ':') !== FALSE)
				{
					$server_addr = '['.$_SERVER['SERVER_ADDR'].']';
				}
				else
				{
					$server_addr = $_SERVER['SERVER_ADDR'];
				}

				$base_url = (is_https() ? 'https' : 'http').'://'.$server_addr
					.substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], basename($_SERVER['SCRIPT_FILENAME'])));
			}
			else
			{
				$base_url = 'http://localhost/';
			}

			$this->set_item('base_url', $base_url);
		}

		$this->_initialize();

		log_message('info', 'Config Class Initialized');
	}


	/**
	 * Load the EE config file and set the initial values
	 *
	 * @access	private
	 * @return	void
	 */
	public function _initialize()
	{
		// Fetch the config file
		$config =& get_config();

		// Is the config file blank?  If so it means that ExpressionEngine has not been installed yet
		if ( ! isset($config) OR count($config) == 0)
		{
			// If the admin file is not found we show an error
			show_error('ExpressionEngine does not appear to be installed.  If you are accessing this page for the first time, please consult the user guide for installation instructions.', 503);
		}

		// Add the EE config data to the master CI config array
		foreach ($config as $key => $val)
		{
			$this->set_item($key, $val);
		}

		unset($config);

		// Set any config overrides.  These are the items that used to be in
		// the path.php file, which are now located in the main index file
		// @todo - rewrite the feature of 'global_vars' to be out of the index.php
		// file and properly live somewhere in system/user
		global $assign_to_config;

		// Override enable_query_strings to always be false on the frontend
		// and true on the backend. We need this to get the pagination library
		// to behave. ACT and CSS get special treatment (see EE_Input::_sanitize_global)

		$assign_to_config['enable_query_strings'] = FALSE;

		// CP?
		if (defined('REQ') && REQ == 'CP')
		{
			$assign_to_config['enable_query_strings'] = TRUE;
		}

		// ACT exception
		if (isset($_GET['ACT']) && preg_match("/^(\w)+$/i", $_GET['ACT']))
		{
			$assign_to_config['enable_query_strings'] = TRUE;
		}

		// URL exception
		if (isset($_GET['URL']) && $_GET['URL'])
		{
			// no other get values allowed
			$_url = $_GET['URL'];
			$_GET = array();
			$_GET['URL'] = $_url;
			unset($_url);

			$assign_to_config['enable_query_strings'] = TRUE;
		}

		$this->_set_overrides($assign_to_config);

		// Set the default_ini data, used by the sites feature
		$this->default_ini = $this->config;

		if ( ! defined('REQ') OR REQ != 'CP')
		{
			$this->default_ini = array_merge($this->default_ini, $assign_to_config);
		}
	}

	/**
	 * Set configuration overrides
	 *
	 * 	These are configuration exceptions.  In some cases a user might want
	 * 	to manually override a config file setting by adding a variable in
	 * 	the index.php file.  This loop permits this to happen.
	 *
	 * @access	private
	 * @return	void
	 */
	public function _set_overrides($params = array())
	{
		if ( ! is_array($params) OR count($params) == 0)
		{
			return;
		}

		// Assign global variables if they exist
		$this->_global_vars = ( ! isset($params['global_vars']) OR ! is_array($params['global_vars'])) ? array() : $params['global_vars'];

		$exceptions = array();
		foreach (array('site_url', 'site_index', 'site_404', 'template_group', 'template', 'cp_url', 'newrelic_app_name') as $exception)
		{
			if (isset($params[$exception]) AND $params[$exception] != '')
			{
				if ( ! defined('REQ') OR REQ != 'CP' OR $exception == 'cp_url')
				{
					$this->config[$exception] = $params[$exception]; // User/Action
				}
				else
				{
					$exceptions[$exception] = $params[$exception];  // CP
				}
			}
		}

		$this->exceptions = $exceptions;

		unset($params);
		unset($exceptions);
	}

	// --------------------------------------------------------------------

	/**
	 * Load Config File
	 *
	 * @param	string	$file			Configuration file name
	 * @param	bool	$use_sections		Whether configuration values should be loaded into their own section
	 * @param	bool	$fail_gracefully	Whether to just return FALSE or display an error message
	 * @return	bool	TRUE if the file was loaded correctly or FALSE on failure
	 */
	public function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
	{
		$file = ($file === '') ? 'config' : str_replace('.php', '', $file);
		$loaded = FALSE;

		foreach ($this->_config_paths as $path)
		{
			foreach (array($file, ENVIRONMENT.DIRECTORY_SEPARATOR.$file) as $location)
			{
				$file_path = resolve_path($path,'config').'/'.$location.'.php';
				if (in_array($file_path, $this->is_loaded, TRUE))
				{
					return TRUE;
				}

				if ( ! file_exists($file_path))
				{
					continue;
				}

				include($file_path);

				if ( ! isset($config) OR ! is_array($config))
				{
					if ($fail_gracefully === TRUE)
					{
						return FALSE;
					}

					show_error('Your '.$file_path.' file does not appear to contain a valid configuration array.');
				}

				if ($use_sections === TRUE)
				{
					$this->config[$file] = isset($this->config[$file])
						? array_merge($this->config[$file], $config)
						: $config;
				}
				else
				{
					$this->config = array_merge($this->config, $config);
				}

				$this->is_loaded[] = $file_path;
				$config = NULL;
				$loaded = TRUE;
				log_message('debug', 'Config file loaded: '.$file_path);
			}
		}

		if ($loaded === TRUE)
		{
			return TRUE;
		}
		elseif ($fail_gracefully === TRUE)
		{
			return FALSE;
		}

		show_error('The configuration file '.$file.'.php does not exist.');
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item
	 *
	 * @param	string	$item	Config item name
	 * @param	string	$index	Index name
	 * @return	string|null	The configuration item or NULL if the item doesn't exist
	 */
	public function item($item, $index = '')
	{
		if ($index == '')
		{
			return isset($this->config[$item]) ? $this->config[$item] : NULL;
		}

		return isset($this->config[$index], $this->config[$index][$item]) ? $this->config[$index][$item] : NULL;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item with slash appended (if not empty)
	 *
	 * @param	string		$item	Config item name
	 * @return	string|null	The configuration item or NULL if the item doesn't exist
	 */
	public function slash_item($item)
	{
		if ( ! isset($this->config[$item]))
		{
			return NULL;
		}
		elseif (trim($this->config[$item]) === '')
		{
			return '';
		}

		return rtrim($this->config[$item], '/').'/';
	}

	// --------------------------------------------------------------------

	/**
	 * Site URL
	 *
	 * Returns base_url . index_page [. uri_string]
	 *
	 * @uses	CI_Config::_uri_string()
	 *
	 * @param	string|string[]	$uri	URI string or an array of segments
	 * @param	string	$protocol
	 * @return	string
	 */
	public function site_url($uri = '', $protocol = NULL)
	{
		$base_url = $this->slash_item('base_url');

		if (isset($protocol))
		{
			// For protocol-relative links
			if ($protocol === '')
			{
				$base_url = substr($base_url, strpos($base_url, '//'));
			}
			else
			{
				$base_url = $protocol.substr($base_url, strpos($base_url, '://'));
			}
		}

		if (empty($uri))
		{
			return $base_url.$this->item('index_page');
		}

		$uri = $this->_uri_string($uri);

		if ($this->item('enable_query_strings') === FALSE)
		{
			$suffix = isset($this->config['url_suffix']) ? $this->config['url_suffix'] : '';

			if ($suffix !== '')
			{
				if (($offset = strpos($uri, '?')) !== FALSE)
				{
					$uri = substr($uri, 0, $offset).$suffix.substr($uri, $offset);
				}
				else
				{
					$uri .= $suffix;
				}
			}

			return $base_url.$this->slash_item('index_page').$uri;
		}
		elseif (strpos($uri, '?') === FALSE)
		{
			$uri = '?'.$uri;
		}

		return $base_url.$this->item('index_page').$uri;
	}

	// -------------------------------------------------------------

	/**
	 * Base URL
	 *
	 * Returns base_url [. uri_string]
	 *
	 * @uses	CI_Config::_uri_string()
	 *
	 * @param	string|string[]	$uri	URI string or an array of segments
	 * @param	string	$protocol
	 * @return	string
	 */
	public function base_url($uri = '', $protocol = NULL)
	{
		$base_url = $this->slash_item('base_url');

		if (isset($protocol))
		{
			// For protocol-relative links
			if ($protocol === '')
			{
				$base_url = substr($base_url, strpos($base_url, '//'));
			}
			else
			{
				$base_url = $protocol.substr($base_url, strpos($base_url, '://'));
			}
		}

		return $base_url.$this->_uri_string($uri);
	}

	// -------------------------------------------------------------

	/**
	 * Build URI string
	 *
	 * @used-by	CI_Config::site_url()
	 * @used-by	CI_Config::base_url()
	 *
	 * @param	string|string[]	$uri	URI string or an array of segments
	 * @return	string
	 */
	protected function _uri_string($uri)
	{
		if ($this->item('enable_query_strings') === FALSE)
		{
			is_array($uri) && $uri = implode('/', $uri);
			return ltrim($uri, '/');
		}
		elseif (is_array($uri))
		{
			return http_build_query($uri);
		}

		return $uri;
	}

	// --------------------------------------------------------------------

	/**
	 * System URL
	 *
	 * @deprecated	3.0.0	Encourages insecure practices
	 * @return	string
	 */
	public function system_url()
	{
		$x = explode('/', preg_replace('|/*(.+?)/*$|', '\\1', BASEPATH));
		return $this->slash_item('base_url').end($x).'/';
	}

	// --------------------------------------------------------------------

	/**
	 * Set a config file item
	 *
	 * @param	string	$item	Config item key
	 * @param	string	$value	Config item value
	 * @return	void
	 */
	public function set_item($item, $value)
	{
		$this->config[$item] = $value;
	}

	public function _assign_to_config($items = array())
	{
		if (is_array($items))
		{
			foreach ($items as $key => $val)
			{
				$this->set_item($key, $val);
			}
		}
	}
}
