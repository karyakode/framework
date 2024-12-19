<?php namespace Flame\Libraries\Driver;

defined('BASEPATH') OR exit('No direct script access allowed');
use ReflectionObject;
/**
 * CodeIgniter Driver Library Class
 *
 * This class enables you to create "Driver" libraries that add runtime ability
 * to extend the capabilities of a class via additional driver objects
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link
 */
class Library {

	/**
	 * Array of drivers that are available to use with the driver class
	 *
	 * @var array
	 */
	protected $valid_drivers = array();

	/**
	 * Name of the current class - usually the driver class
	 *
	 * @var string
	 */
	protected $lib_name;

	/**
	 * Get magic method
	 *
	 * The first time a child is used it won't exist, so we instantiate it
	 * subsequents calls will go straight to the proper child.
	 *
	 * @param	string	Child class name
	 * @return	object	Child class
	 */
	public function __get($child)
	{
		// Try to load the driver
		return $this->load_driver($child);
	}

	/**
	 * Load driver
	 *
	 * Separate load_driver call to support explicit driver load by library or user
	 *
	 * @param	string	Driver name (w/o parent prefix)
	 * @return	object	Child class
	 */


	 public function load_driver($child)
 	{
		// Get CodeIgniter instance and subclass prefix
		$prefix = config_item('subclass_prefix');

		if ( ! isset($this->lib_name))
		{
			// Get library name without any prefix
			$this->lib_name = str_replace(array('CI_', $prefix), '', get_class($this));
		}

		// The child will be prefixed with the parent lib
		$lib_name = $this->lib_name;

		if(strpos($lib_name,'\\')){
			$lib_name = basename(str_replace('\\','/',$lib_name));
		}

		$class_name = 'Flame\Libraries\\'.$lib_name.'\Drivers\\'.ucwords($child);

		if(class_exists($class_name)) {
			// Instantiate, decorate and add child
			$obj = new $class_name();
			$obj->decorate($this);
			$this->$child = $obj;
			return $this->$child;
		}

		return $this->load_legacy($child);
 	}


	public function load_legacy($child)
	{
		// Get CodeIgniter instance and subclass prefix
		$prefix = config_item('subclass_prefix');

		if ( ! isset($this->lib_name))
		{
			// Get library name without any prefix
			$this->lib_name = str_replace(array('CI_', $prefix), '', get_class($this));
		}

		// The child will be prefixed with the parent lib
		$child_name = $this->lib_name.'_'.$child;

		// See if requested child is a valid driver
		if ( ! in_array($child, $this->valid_drivers))
		{
			// The requested driver isn't valid!
			$msg = 'Invalid driver requested: '.$child_name;
			log_message('error', $msg);
			show_error($msg);
		}

		// Get package paths and filename case variations to search
		$CI = flame();
		$paths = $CI->load->get_package_paths(TRUE);

		// Is there an extension?
		$class_name = $prefix.$child_name;
		$found = class_exists($class_name, FALSE);
		if ( ! $found)
		{
			// Check for subclass file
			foreach ($paths as $path)
			{

				// Does the file exist?
				$file = resolve_path($path,'libraries').'/'.$this->lib_name.'/Drivers/'.$prefix.$child_name.'.php';
				if (file_exists($file))
				{

					// Yes - require base class from BASEPATH
					$basepath = resolve_path(BASEPATH,'libraries').'/'.$this->lib_name.'/Drivers/'.$child_name.'.php';
					if ( ! file_exists($basepath))
					{
						$msg = 'Unable to load the requested class: CI_'.$child_name;
						log_message('error', $msg);
						show_error($msg);
					}

					// Include both sources and mark found
					include_once($basepath);
					include_once($file);
					$found = TRUE;
					break;
				}
			}
		}

		// Do we need to search for the class?
		if ( ! $found)
		{
			// Use standard class name
			$class_name = 'CI_'.$child_name;
			if ( ! class_exists($class_name, FALSE))
			{
				// Check package paths
				foreach ($paths as $path)
				{
					// Does the file exist?
					$file = resolve_path($path,'libraries').'/'.$this->lib_name.'/Drivers/'.$child_name.'.php';
					if (file_exists($file))
					{
						// Include source
						include_once($file);
						break;
					}
				}
			}
		}

		// Did we finally find the class?
		if ( ! class_exists($class_name, FALSE))
		{
			if (class_exists($child_name, FALSE))
			{
				$class_name = $child_name;
			}
			else
			{
				$msg = 'Unable to load the requested driver: '.$class_name;
				log_message('error', $msg);
				show_error($msg);
			}
		}

		// Instantiate, decorate and add child
		$obj = new $class_name();
		$obj->decorate($this);
		$this->$child = $obj;
		return $this->$child;
	}

}
