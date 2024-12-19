<?php namespace Flame\Database;

class DB extends \Flame\Database\Builder\Builder { }

class Database
{
  function __construct(){

  }
  public static function DB($params = '', $query_builder_override = NULL){

    $params = self::load_config($params);

  	// No DB specified yet? Beat them senseless...
  	if (empty($params['dbdriver']))
  	{
  		show_error('You have not selected a database type to connect to.');
  	}

  	$DB = self::load_driver($params);

  	$DB->initialize();
  	return $DB;
  }


  private static function load_config($params){

  	// Load the DB config file if a DSN string wasn't passed
  	if (is_string($params) && strpos($params, '://') === FALSE)
  	{
  		// Is the config file in the environment folder?
  		if ( ! file_exists($file_path = resolve_path(APPPATH,'config').'/'.ENVIRONMENT.'/database.php')
  			&& ! file_exists($file_path = resolve_path(APPPATH,'config').'/database.php'))
  		{
  			show_error('The configuration file database.php does not exist.');
  		}

  		include($file_path);

  		// Make packages contain database config files,
  		// given that the controller instance already exists
  		if (class_exists('CI_Controller', FALSE))
  		{
  			foreach (flame()->load->get_package_paths() as $path)
  			{
  				if ($path !== APPPATH)
  				{
  					if (file_exists($file_path = resolve_path($path,'config').'/'.ENVIRONMENT.'/database.php'))
  					{
  						include($file_path);
  					}
  					elseif (file_exists($file_path = resolve_path($path,'config').'/database.php'))
  					{
  						include($file_path);
  					}
  				}
  			}
  		}

  		if ( ! isset($db) OR count($db) === 0)
  		{
  			show_error('No database connection settings were found in the database config file.');
  		}

  		if ($params !== '')
  		{
  			$active_group = $params;
  		}

  		if ( ! isset($active_group))
  		{
  			show_error('You have not specified a database connection group via $active_group in your config/database.php file.');
  		}
  		elseif ( ! isset($db[$active_group]))
  		{
  			show_error('You have specified an invalid database connection group ('.$active_group.') in your config/database.php file.');
  		}

  		$params = $db[$active_group];
  	}
  	elseif (is_string($params))
  	{
  		/**
  		 * Parse the URL from the DSN string
  		 * Database settings can be passed as discreet
  		 * parameters or as a data source name in the first
  		 * parameter. DSNs must have this prototype:
  		 * $dsn = 'driver://username:password@hostname/database';
  		 */
  		if (($dsn = @parse_url($params)) === FALSE)
  		{
  			show_error('Invalid DB Connection String');
  		}

  		$params = array(
  			'dbdriver'	=> $dsn['scheme'],
  			'hostname'	=> isset($dsn['host']) ? rawurldecode($dsn['host']) : '',
  			'port'		=> isset($dsn['port']) ? rawurldecode($dsn['port']) : '',
  			'username'	=> isset($dsn['user']) ? rawurldecode($dsn['user']) : '',
  			'password'	=> isset($dsn['pass']) ? rawurldecode($dsn['pass']) : '',
  			'database'	=> isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : ''
  		);

  		// Were additional config items set?
  		if (isset($dsn['query']))
  		{
  			parse_str($dsn['query'], $extra);

  			foreach ($extra as $key => $val)
  			{
  				if (is_string($val) && in_array(strtoupper($val), array('TRUE', 'FALSE', 'NULL')))
  				{
  					$val = var_export($val, TRUE);
  				}

  				$params[$key] = $val;
  			}
  		}
  	}

  	return $params;
  }


  private static function load_driver($params){

		$driver = 'Flame\Database\Driver\Drivers\\'.ucwords($params['dbdriver']).'\Driver';
  	$DB = new $driver($params);

  	// Check for a subdriver
  	if ( ! empty($DB->subdriver))
  	{
			$driver = 'Flame\Database\Driver\Drivers\\'.ucwords($params['dbdriver']).'\Subdrivers\\'.ucwords($DB->subdriver).'\Driver';

			$DB = new $driver($params);
  	}

  	return $DB;
  }

}

new Database;
