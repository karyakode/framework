<?php namespace Kodhe\Pulen\Framework\Loader\Plugin;

use Kodhe\Pulen\Framework\Modules\Module;

class Plugin
{

  protected $_kodhe_plugins = [];

  function __construct()
  {

  }

  public function plugin($plugin)
	{
			if (is_array($plugin)) {
					return $this->plugins($plugin);
			}

			if (isset($this->_kodhe_plugins[$plugin])) {
					return $this;
			}

			// Backward function
			// Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
			if (version_compare(phpversion(), '7.1', '<')) {
					// php version isn't high enough
					list($path, $_plugin) = Module::find($plugin.'_pi', 'plugins/');
			} else {
					[$path, $_plugin] = Module::find($plugin.'_pi', 'plugins/');
			}

			if ($path === false && ! is_file($_plugin = resolve_path(APPPATH,'plugins').'/'.$_plugin.'.php')) {
					show_error("Unable to locate the plugin file: {$_plugin}");
			}

			Module::load_file($_plugin, $path);
			$this->_kodhe_plugins[$plugin] = true;
			return $this;
	}

	protected function plugins($plugins)
	{
			foreach ($plugins as $_plugin) {
					$this->plugin($_plugin);
			}
			return $this;
	}

}
