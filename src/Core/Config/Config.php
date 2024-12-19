<?php namespace Flame\Core\Config;
defined('BASEPATH') OR exit('No direct script access allowed');

use Flame\Core\Module\Module;

class Config extends Legacy implements ConfigInterface
{
  public function __construct(){
    parent::__construct();
  }

  public function make($file, $use_sections = false, $fail_gracefully = false){
    if(is_array($file)) {
      foreach ($file as $key => $value) {
        if (is_int($key))
        {
          flame()->set($value,$this);
          return $this->load($value, $use_sections, $fail_gracefully);
        } else {
          flame()->set($value,$this);
          return $this->load($key, $use_sections, $fail_gracefully);
        }
      }
    }


    return $this->load($file, $use_sections, $fail_gracefully);
  }

    public function load($file = '', $use_sections = false, $fail_gracefully = false, $_module = '')
    {
        if (in_array($file, $this->is_loaded, true)) {
            return $this->item($file);
        }

        $_module or $_module = flame()->router->fetch_module();

        // Backward function
        // Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
        if (version_compare(phpversion(), '7.1', '<')) {
            // php version isn't high enough
            list($path, $file) = Module::find($file, 'config/', $_module);
        } else {
            [$path, $file] = Module::find($file, 'config/', $_module);
        }

        if ($path === false) {
            parent::load($file, $use_sections, $fail_gracefully);
            return $this->item($file);
        }

        if ($config = Module::load_file($file, $path, 'config')) {
            // reference to the config array
            $current_config =& $this->config;

            if ($use_sections === true) {
                if (isset($current_config[$file])) {
                    $current_config[$file] = array_merge($current_config[$file], $config);
                } else {
                    $current_config[$file] = $config;
                }
            } else {
                $current_config = array_merge($current_config, $config);
            }


            $this->is_loaded[] = $file;
            unset($config);
            return $this->item($file);
        }
    }
}
