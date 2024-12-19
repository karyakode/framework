<?php namespace Flame\Core\Loader\Package;

class Package
{

  protected $_flame_view_paths;
  protected $_flame_library_paths;
  protected $_flame_model_paths;
  protected $_flame_helper_paths;


  function __construct(\Flame\Core\Path\Paths $Paths)
  {

    $this->_flame_view_paths = $Paths::$viewPaths;
    $this->_flame_library_paths = $Paths::$libraryPaths;
    $this->_flame_model_paths = $Paths::$modelPaths;
    $this->_flame_helper_paths = $Paths::$helperPaths;

  }

  public function add_path($path, $view_cascade = TRUE)
	{
		$path = rtrim($path, '/').'/';

		array_unshift($this->_flame_library_paths, $path);
		array_unshift($this->_flame_model_paths, $path);
		array_unshift($this->_flame_helper_paths, $path);

		$this->_flame_view_paths = array(resolve_path($path,'views').'/' => $view_cascade) + $this->_flame_view_paths;

		// Add config file path
		$config =& $this->_flame_get_component('config');
		$config->_config_paths[] = $path;

		return $this;
	}

	public function get_paths($include_base = FALSE)
	{
		return ($include_base === TRUE) ? $this->_flame_library_paths : $this->_flame_model_paths;
	}

	public function remove_path($path = '')
	{
		$config =& $this->_flame_get_component('config');

		if ($path === '')
		{
			array_shift($this->_flame_library_paths);
			array_shift($this->_flame_model_paths);
			array_shift($this->_flame_helper_paths);
			array_shift($this->_flame_view_paths);
			array_pop($config->_config_paths);
		}
		else
		{
			$path = rtrim($path, '/').'/';
			foreach (array('_flame_library_paths', '_flame_model_paths', '_flame_helper_paths') as $var)
			{
				if (($key = array_search($path, $this->{$var})) !== FALSE)
				{
					unset($this->{$var}[$key]);
				}
			}

			if (isset($this->_flame_view_paths[resolve_path($path,'views').'/']))
			{
				unset($this->_flame_view_paths[resolve_path($path,'views').'/']);
			}

			if (($key = array_search($path, $config->_config_paths)) !== FALSE)
			{
				unset($config->_config_paths[$key]);
			}
		}

		// make sure the application default paths are still in the array
		$this->_flame_library_paths = array_unique(array_merge($this->_flame_library_paths, array(APPPATH, BASEPATH)));
		$this->_flame_helper_paths = array_unique(array_merge($this->_flame_helper_paths, array(APPPATH, BASEPATH)));
		$this->_flame_model_paths = array_unique(array_merge($this->_flame_model_paths, array(APPPATH)));
		$this->_flame_view_paths = array_merge($this->_flame_view_paths, array(resolve_path(APPPATH,'views').'/' => TRUE));
		$config->_config_paths = array_unique(array_merge($config->_config_paths, array(APPPATH)));

		return $this;
	}

	protected function &_flame_get_component($component)
	{
		return flame()->$component;
	}

}
