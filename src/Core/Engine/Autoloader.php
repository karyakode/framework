<?php

namespace Flame\Core\Engine;

class Autoloader {

	protected $prefixes = array('Flame'=> BASEPATH);

	protected static $instance;

	/**
	 * Use as a singleton
	 */
	public static function getInstance()
	{
		if ( ! isset(static::$instance))
		{
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Register the autoloader with PHP
	 */
	public function register()
	{

		spl_autoload_register(array($this, 'loadClass'));

		return $this;
	}

	/**
	 * Remove the autoloader
	 */
	public function unregister()
	{
		spl_autoload_unregister(array($this, 'loadClass'));

		return $this;
	}

	/**
	 * Map a namespace prefix to a path
	 */
	public function addPrefix($namespace, $path)
	{
		if(!empty($this->prefixes[$namespace])) return;

		$this->prefixes[$namespace] = $path;

		return $this;
	}

	/**
	 * Map a namespace prefix in a path
	 */
	public function hasPrefix($namespace)
	{

		return (!empty($this->prefixes[$namespace]) ? true : false);
	}

	/**
	 * Handle the autoload call.
	 *
	 * @param String $class Fully qualified class name. As of 5.3.3 this does
	 *                      not include a leading slash.
	 * @return void
	 */
	public function loadClass($class)
	{
		// @todo this prefix handling will not do sub-namespaces correctly
		foreach ($this->prefixes as $prefix => $path)
		{
			if (empty($prefix))
			{
				throw new \Exception("No namespace specified for add-on: {$path}");
			}


			if (strpos($class, $prefix) === 0)
			{
				// From inside to out: Strip off the prefix from the namespace, turn the namespace into
				// a path, prepend the path prefix, append .php.
				$class_name = str_replace(['\\','CI_'], ['/',''], substr($class, strlen($prefix)));
				$class_alias = strtolower(basename($class_name));



				$class_path = $path . $class_name . '.php';

				if (file_exists($class_path) && !class_exists($class))
				{
					require_once $class_path;

					return;
				}
			}
		}

		// Keep this commented out until we're fully namespaced. PHP will handle it.
		//throw new \RuntimeException('Failed to load class: ' . $class . '!');
	}
}


// EOF
