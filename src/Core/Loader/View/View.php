<?php namespace Flame\Core\Loader\View;

use Flame\Core\Module\Module;

class View extends \Flame\Core\Engine\BaseController {

	protected $_flame_ob_level;
	protected $_flame_view_paths;
	protected $_flame_cached_vars =	array();


	public function __construct(\Flame\Core\Path\Paths $Paths){
		$this->_flame_ob_level = ob_get_level();
		$this->_flame_view_paths = $Paths::$viewPaths;
		log_message('info', 'View Class Initialized');
	}


	public function make($view, $vars = [], $return = false){

			[$path, $_view] = Module::find($view, 'views/');

			if ($path != false) {
					$this->_flame_view_paths = [$path => true] + $this->_flame_view_paths;
					$view = $_view;
			}

			return $this->view_legacy($view, $vars, $return);
	}

	 public function view_legacy($view, $vars = array(), $return = FALSE){
 		$ee_only = array();
 		$orig_paths = $this->_flame_view_paths;

 		foreach (array_reverse($orig_paths, TRUE) as $path => $cascade)
 		{
 			if ($cascade === FALSE)
 			{
 				break;
 			}

 			$ee_only[$path] = TRUE;
 		}

 		// Temporarily replace them, load the view, and back again
 		$this->_flame_view_paths = array_reverse($ee_only, TRUE);

		$vars['flame'] = flame();

		if (method_exists($this, '_flame_object_to_array')) {
				$ret = $this->_flame_load(['_flame_view' => $view, '_flame_vars' => $this->_flame_object_to_array($vars), '_flame_return' => $return]);
		} else {
				$ret = $this->_flame_load(['_flame_view' => $view, '_flame_vars' => $this->_flame_prepare_view_vars($vars), '_flame_return' => $return]);
		}
 		$this->_flame_view_paths = $orig_paths;

 		return $ret;
 	}

	public function file($path, $return = FALSE)
	{
		return $this->_flame_load(array('_flame_path' => $path, '_flame_return' => $return));
	}

	public function vars($vars, $val = '')
	{
		$vars = is_string($vars)
			? array($vars => $val)
			: $this->_flame_prepare_view_vars($vars);

		foreach ($vars as $key => $val)
		{
			$this->_flame_cached_vars[$key] = $val;
		}

		return $this;
	}

	public function clear_vars()
	{
		$this->_flame_cached_vars = array();
		return $this;
	}

	public function get_var($key)
	{
		return isset($this->_flame_cached_vars[$key]) ? $this->_flame_cached_vars[$key] : NULL;
	}

	public function get_vars()
	{
		return $this->_flame_cached_vars;
	}


	public function _flame_load($_flame_data)
	{
		// Set the default data variables
		foreach (array('_flame_view', '_flame_vars', '_flame_path', '_flame_return') as $_flame_val)
		{
			$$_flame_val = isset($_flame_data[$_flame_val]) ? $_flame_data[$_flame_val] : FALSE;
		}

		$file_exists = FALSE;

		// Set the path to the requested file
		if (is_string($_flame_path) && $_flame_path !== '')
		{
			$_flame_x = explode('/', $_flame_path);
			$_flame_file = end($_flame_x);
		}
		else
		{
			$_flame_ext = pathinfo($_flame_view, PATHINFO_EXTENSION);
			$_flame_file = ($_flame_ext === '') ? $_flame_view.'.php' : $_flame_view;

			foreach ($this->_flame_view_paths as $_flame_view_file => $cascade)
			{
				if (file_exists($_flame_view_file.$_flame_file))
				{
					$_flame_path = $_flame_view_file.$_flame_file;
					$file_exists = TRUE;
					break;
				}

				if ( ! $cascade)
				{
					break;
				}
			}
		}

		if ( ! $file_exists && ! file_exists($_flame_path))
		{
			show_error('Unable to load the requested file: '.$_flame_file);
		}

		// This allows anything loaded using $this->load (views, files, etc.)
		// to become accessible from within the Controller and Model functions.
		$_flame_CI = flame();
		foreach (get_object_vars($_flame_CI) as $_flame_key => $_flame_var)
		{
			/*if ( ! isset(flame()->$_flame_key))
			{
				flame()->$_flame_key =& $_flame_CI->$_flame_key;
			} elseif(empty($this->$_flame_key) && empty(flame()->$_flame_key)) {
				$this->$_flame_key =& $_flame_CI->$_flame_key;
			}elseif(empty($this->$_flame_key) && !empty(flame()->$_flame_key)) {
				$this->$_flame_key = flame()->$_flame_key;
			}*/

			if(empty(flame()->has($_flame_key))) {
				flame()->set($_flame_key, $_flame_var);
			}

		}



		empty($_flame_vars) OR $this->_flame_cached_vars = array_merge($this->_flame_cached_vars, $_flame_vars);
		extract($this->_flame_cached_vars);

		ob_start();

		if ( ! is_php('5.4') && ! ini_get('short_open_tag') && config_item('rewrite_short_tags') === TRUE)
		{
			echo eval('?>'.preg_replace('/;*\s*\?>/', '; ?>', str_replace('<?=', '<?php echo ', file_get_contents($_flame_path))));
		}
		else
		{
			include_once($_flame_path); // include() vs include_once() allows for multiple views with the same name
		}

		log_message('info', 'File loaded: '.$_flame_path);

		// Return the file data if requested
		if ($_flame_return === TRUE)
		{
			$buffer = ob_get_contents();
			@ob_end_clean();
			return $buffer;
		}

		if (ob_get_level() > $this->_flame_ob_level + 1)
		{
			ob_end_flush();
		}
		else
		{
			$_flame_CI->output->append_output(ob_get_contents());
			@ob_end_clean();
		}

		return $this;
	}



	public function _flame_prepare_view_vars($vars)
	{
		if ( ! is_array($vars))
		{
			$vars = is_object($vars)
				? get_object_vars($vars)
				: array();
		}

		foreach (array_keys($vars) as $key)
		{
			if (strncmp($key, '_flame_', 4) === 0)
			{
				unset($vars[$key]);
			}
		}

		return $vars;
	}

}
