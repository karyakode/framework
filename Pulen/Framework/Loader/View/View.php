<?php namespace Kodhe\Pulen\Framework\Loader\View;

use Kodhe\Pulen\Framework\Modules\Module;

class View extends \Kodhe\Pulen\Framework\Application\BaseController {

	protected $_kodhe_ob_level;
	protected $_kodhe_view_paths;
	protected $_kodhe_cached_vars =	array();


	public function __construct(\Kodhe\Pulen\Framework\Support\Path\Paths $Paths){
		$this->_kodhe_ob_level = ob_get_level();
		$this->_kodhe_view_paths = $Paths::$viewPaths;
		log_message('info', 'View Class Initialized');
	}


	public function make($view, $vars = [], $return = false){

			[$path, $_view] = Module::find($view, 'views/');

			if ($path != false) {
					$this->_kodhe_view_paths = [$path => true] + $this->_kodhe_view_paths;
					$view = $_view;
			}

			return $this->view_legacy($view, $vars, $return);
	}

	 public function view_legacy($view, $vars = array(), $return = FALSE){
 		$ee_only = array();
 		$orig_paths = $this->_kodhe_view_paths;

 		foreach (array_reverse($orig_paths, TRUE) as $path => $cascade)
 		{
 			if ($cascade === FALSE)
 			{
 				break;
 			}

 			$ee_only[$path] = TRUE;
 		}

 		// Temporarily replace them, load the view, and back again
 		$this->_kodhe_view_paths = array_reverse($ee_only, TRUE);

		if (method_exists($this, '_kodhe_object_to_array')) {
				$ret = $this->_kodhe_load(['_kodhe_view' => $view, '_kodhe_vars' => $this->_kodhe_object_to_array($vars), '_kodhe_return' => $return]);
		} else {
				$ret = $this->_kodhe_load(['_kodhe_view' => $view, '_kodhe_vars' => $this->_kodhe_prepare_view_vars($vars), '_kodhe_return' => $return]);
		}
 		$this->_kodhe_view_paths = $orig_paths;

 		return $ret;
 	}

	public function file($path, $return = FALSE)
	{
		return $this->_kodhe_load(array('_kodhe_path' => $path, '_kodhe_return' => $return));
	}

	public function vars($vars, $val = '')
	{
		$vars = is_string($vars)
			? array($vars => $val)
			: $this->_kodhe_prepare_view_vars($vars);

		foreach ($vars as $key => $val)
		{
			$this->_kodhe_cached_vars[$key] = $val;
		}

		return $this;
	}

	public function clear_vars()
	{
		$this->_kodhe_cached_vars = array();
		return $this;
	}

	public function get_var($key)
	{
		return isset($this->_kodhe_cached_vars[$key]) ? $this->_kodhe_cached_vars[$key] : NULL;
	}

	public function get_vars()
	{
		return $this->_kodhe_cached_vars;
	}


	public function _kodhe_load($_kodhe_data)
	{
		// Set the default data variables
		foreach (array('_kodhe_view', '_kodhe_vars', '_kodhe_path', '_kodhe_return') as $_kodhe_val)
		{
			$$_kodhe_val = isset($_kodhe_data[$_kodhe_val]) ? $_kodhe_data[$_kodhe_val] : FALSE;
		}

		$file_exists = FALSE;

		// Set the path to the requested file
		if (is_string($_kodhe_path) && $_kodhe_path !== '')
		{
			$_kodhe_x = explode('/', $_kodhe_path);
			$_kodhe_file = end($_kodhe_x);
		}
		else
		{
			$_kodhe_ext = pathinfo($_kodhe_view, PATHINFO_EXTENSION);
			$_kodhe_file = ($_kodhe_ext === '') ? $_kodhe_view.'.php' : $_kodhe_view;

			foreach ($this->_kodhe_view_paths as $_kodhe_view_file => $cascade)
			{
				if (file_exists($_kodhe_view_file.$_kodhe_file))
				{
					$_kodhe_path = $_kodhe_view_file.$_kodhe_file;
					$file_exists = TRUE;
					break;
				}

				if ( ! $cascade)
				{
					break;
				}
			}
		}

		if ( ! $file_exists && ! file_exists($_kodhe_path))
		{
			show_error('Unable to load the requested file: '.$_kodhe_file);
		}

		// This allows anything loaded using $this->load (views, files, etc.)
		// to become accessible from within the Controller and Model functions.
		$_kodhe_CI = kodhe();
		foreach (get_object_vars($_kodhe_CI) as $_kodhe_key => $_kodhe_var)
		{
			/*if ( ! isset(kodhe()->$_kodhe_key))
			{
				kodhe()->$_kodhe_key =& $_kodhe_CI->$_kodhe_key;
			} elseif(empty($this->$_kodhe_key) && empty(kodhe()->$_kodhe_key)) {
				$this->$_kodhe_key =& $_kodhe_CI->$_kodhe_key;
			}elseif(empty($this->$_kodhe_key) && !empty(kodhe()->$_kodhe_key)) {
				$this->$_kodhe_key = kodhe()->$_kodhe_key;
			}*/

			if(empty(kodhe()->has($_kodhe_key))) {
				kodhe()->set($_kodhe_key, $_kodhe_var);
			}

		}



		empty($_kodhe_vars) OR $this->_kodhe_cached_vars = array_merge($this->_kodhe_cached_vars, $_kodhe_vars);
		extract($this->_kodhe_cached_vars);

		ob_start();

		if ( ! is_php('5.4') && ! ini_get('short_open_tag') && config_item('rewrite_short_tags') === TRUE)
		{
			echo eval('?>'.preg_replace('/;*\s*\?>/', '; ?>', str_replace('<?=', '<?php echo ', file_get_contents($_kodhe_path))));
		}
		else
		{
			include_once($_kodhe_path); // include() vs include_once() allows for multiple views with the same name
		}

		log_message('info', 'File loaded: '.$_kodhe_path);

		// Return the file data if requested
		if ($_kodhe_return === TRUE)
		{
			$buffer = ob_get_contents();
			@ob_end_clean();
			return $buffer;
		}

		if (ob_get_level() > $this->_kodhe_ob_level + 1)
		{
			ob_end_flush();
		}
		else
		{
			$_kodhe_CI->output->append_output(ob_get_contents());
			@ob_end_clean();
		}

		return $this;
	}



	public function _kodhe_prepare_view_vars($vars)
	{
		if ( ! is_array($vars))
		{
			$vars = is_object($vars)
				? get_object_vars($vars)
				: array();
		}

		foreach (array_keys($vars) as $key)
		{
			if (strncmp($key, '_kodhe_', 4) === 0)
			{
				unset($vars[$key]);
			}
		}

		return $vars;
	}

}
