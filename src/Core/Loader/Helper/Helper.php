<?php namespace Kodhe\Pulen\Core\Loader\Helper;

use Kodhe\Pulen\Core\Module\Module;

class Helper {

	public $_kodhe_helpers =	array();
	protected $_kodhe_helper_paths;

  public function __construct(\Kodhe\Pulen\Core\Path\Paths $Paths)
	{
		$this->_kodhe_helper_paths = $Paths::$helperPaths;
	}

  public function make($helper = [])
  {
      if (is_array($helper)) {
          return $this->helper_legacy($helper);
      }

      if (isset($this->_kodhe_helpers[$helper])) {
          return;
      }

			[$path, $_helper] = Module::find($helper.'_helper', 'helpers/');

      if ($path === false) {
          return $this->helper_legacy($helper);
      }

      Module::load_file($_helper, $path);
      $this->_kodhe_helpers[$_helper] = true;
      return $this;
  }

  public function helper_legacy($helpers = [])
  {
      $helpers = (array) $helpers;

      foreach ($helpers as $helper) {
          $helper = $this->normalize_helper_name($helper);

          if (isset($this->_kodhe_helpers[$helper])) {
              continue;
          }

          if ($this->load_helper_extension($helper)) {
              $this->_kodhe_helpers[$helper] = TRUE;
              continue;
          }

          if (!$this->load_helper($helper)) {
              show_error("Unable to load the requested file: helpers/{$helper}.php");
          }
      }

      return $this;
  }

  protected function normalize_helper_name($helper)
  {
      $filename = basename($helper);
      $filepath = ($filename === $helper) ? '' : substr($helper, 0, strlen($helper) - strlen($filename));
      $filename = strtolower(preg_replace('#(_helper)?(\.php)?$#i', '', $filename)) . '_helper';
      return $filepath . $filename;
  }

  protected function load_helper_extension($helper)
  {
      $ext_helper = config_item('subclass_prefix') . basename($helper);

      foreach ($this->_kodhe_helper_paths as $path) {
          $helper_path = resolve_path($path, 'helpers');

          if (file_exists("{$helper_path}/{$ext_helper}.php")) {
              include_once("{$helper_path}/{$ext_helper}.php");
              $this->load_base_helper($helper);
              log_message('info', "Helper extension loaded: {$ext_helper}");
              return TRUE;
          }
      }

      return FALSE;
  }

  protected function load_base_helper($helper)
  {
      echo $base_helper_path = VENDORPATH. 'Helpers/legacy' . "/{$helper}.php";

      if (!file_exists($base_helper_path)) {
          show_error("Unable to load the requested file: helpers/{$helper}.php");
      }

      include_once($base_helper_path);
      log_message('info', "Base helper loaded: {$helper}");
  }

  protected function load_helper($helper)
  {
      foreach ($this->_kodhe_helper_paths as $path) {
          $helper_path = resolve_path($path, 'helpers');

          if (file_exists("{$helper_path}/{$helper}.php")) {
              include_once("{$helper_path}/{$helper}.php");
              $this->_kodhe_helpers[$helper] = TRUE;
              log_message('info', "Helper loaded: {$helper}");
              return TRUE;
          }
      }

      return FALSE;
  }

 public function helpers($helpers = array())
 {
   return $this->make($helpers);
 }


  protected function &_kodhe_get_component($component)
	{
		return kodhe()->$component;
	}


}
