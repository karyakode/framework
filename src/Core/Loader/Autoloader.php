<?php namespace Flame\Core\Loader;

use Flame\Core\Module\Module;
use Flame\Core\Path\Paths;

class Autoloader
{

  function __construct()
  {
  }

  public function loader($autoload){
			$path = false;

			if (Module::$getModule) {

					// Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
					if (version_compare(phpversion(), '7.1', '<')) {
							// php version isn't high enough
							list($path, $file) = Module::find('constants', 'config/');
					} else {
							[$path, $file] = Module::find('constants', 'config/');
					}
					// module constants file
					if ($path !== false) {
							include_once $path.$file.'.php';
					}

					// Backward function
					// Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
					if (version_compare(phpversion(), '7.1', '<')) {
							// php version isn't high enough
							list($path, $file) = Module::find('autoload', 'config/');
					} else {
							[$path, $file] = Module::find('autoload', 'config/');
					}

					// module autoload file
					if ($path !== false) {
							$autoload = array_merge(Module::load_file($file, $path, 'autoload'), $autoload);
					}
			}

			// nothing to do
			if (count($autoload) === 0) {
					return;
			}

			// autoload package paths
			if (isset($autoload['packages'])) {
					foreach ($autoload['packages'] as $package_path) {
							$this->add_package_path($package_path);
					}
			}

			/* autoload config */
			if (isset($autoload['config'])) {
					foreach ($autoload['config'] as $config) {
							$this->config($config);
					}
			}

			// autoload helpers, plugins, languages
			foreach (['helper', 'plugin', 'language'] as $type) {
					if (isset($autoload[$type])) {
							foreach ($autoload[$type] as $item) {
									$this->$type($item);
							}
					}
			}

			// Autoload drivers
			if (isset($autoload['drivers'])) {
					foreach ($autoload['drivers'] as $item => $alias) {
							is_int($item) ? $this->driver($alias) : $this->driver($item, $alias);
					}
			}

			// autoload database & libraries
			if (isset($autoload['libraries'])) {
					if (!$db = flame()->config->item('database') && in_array('database', $autoload['libraries'])) {
							Database::database();

							$autoload['libraries'] = array_diff($autoload['libraries'], ['database']);
					}

					// autoload libraries
					foreach ($autoload['libraries'] as $library => $alias) {
							is_int($library) ? $this->library($alias) : $this->library($library, null, $alias);
					}
			}

			// autoload models
			if (isset($autoload['model'])) {
					foreach ($autoload['model'] as $model => $alias) {
							is_int($model) ? $this->model($alias) : $this->model($model, $alias);
					}
			}

			// autoload module controllers
			if (isset($autoload['modules'])) {
					foreach ($autoload['modules'] as $controller) {
							($controller != Module::$getModule) && $this->module($controller);
					}
			}
	}


  public function _flame_autoloader()
	{
		$path_config = resolve_path(APPPATH,'config');

		if (file_exists($path_config.'/autoload.php'))
		{
			include($path_config.'/autoload.php');
		}

		if (file_exists($path_config.'/'.ENVIRONMENT.'/autoload.php'))
		{
			include($path_config.'/'.ENVIRONMENT.'/autoload.php');
		}

		if ( ! isset($autoload))
		{
			return;
		}

		// Autoload packages
		if (isset($autoload['packages']))
		{
			foreach ($autoload['packages'] as $package_path)
			{
				flame()->load->add_package_path($package_path);
			}
		}

		// Load any custom config file
		if (count($autoload['config']) > 0)
		{
			foreach ($autoload['config'] as $val)
			{
				flame()->load->config($val);
			}
		}

		// Autoload helpers and languages
		foreach (array('helper', 'language') as $type)
		{
			if (isset($autoload[$type]) && count($autoload[$type]) > 0)
			{
				flame()->load->$type($autoload[$type]);
			}
		}

		// Autoload drivers
		if (isset($autoload['drivers']))
		{
			flame()->load->driver($autoload['drivers']);
		}

		// Load libraries
		if (isset($autoload['libraries']) && count($autoload['libraries']) > 0)
		{
			// Load the database driver.
			if (in_array('database', $autoload['libraries']))
			{

				isset(flame()->db) OR Database::database();
				$autoload['libraries'] = array_diff($autoload['libraries'], array('database'));
			}

			// Load all other libraries
			flame()->load->library($autoload['libraries']);
		}

		// Autoload models
		if (isset($autoload['model']))
		{
			flame()->load->model($autoload['model']);
		}
	}


}
