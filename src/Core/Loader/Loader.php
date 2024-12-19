<?php namespace Flame\Core\Loader;

use Flame\Core\Module\Module;
use Flame\Core\Facade\Facade;
use Flame\Core\Loader\Plugin\Plugin;
use Flame\Core\Loader\Package\Package;
use Flame\Core\Loader\Library\Library;
use Flame\Core\Loader\Model\Model;
use Flame\Core\Loader\Helper\Helper;
use Flame\Core\Loader\Driver\Driver;
use Flame\Core\Loader\View\View;

class Loader {

	protected $_ci_classes =	array();

	protected $package;
	protected $library;
	protected $model;
	protected $helper;
	protected $autoloader;
	protected $view;
	protected $driver;

	public function __construct(Facade $facade, Package $package, Library $library, Model $model, Helper $helper, View $view, Driver $driver, Autoloader $autoloader){

		$this->set_base_classes();

		$this->package 			= $package;
		$this->library 			= $library;
		$this->model				= $model;
		$this->helper 			= $helper;
		$this->view 				= $view;
		$this->driver 	= $driver;
		$this->autoloader 	= $autoloader;
		$this->facade 	= $facade;

		log_message('info', 'Loader Class Initialized');

	}


	public function set_base_classes(){
		$this->_base_classes = is_loaded();

		return $this;
	}

	public function initialize(){
		// autoload module items
		$this->autoloader->loader([]);
		$this->autoloader->_flame_autoloader();
	}

	protected function _ci_autoloader(){
		$this->autoloader->_flame_autoloader();
	}

	public function is_loaded($class){
		return array_search(ucfirst($class), $this->_ci_classes, TRUE);
	}

	public function library($library, $params = null, $object_name = null)
	{
		$library = $this->library->make($library, $params, $object_name);
		//$this->_ci_classes = array_merge($this->_ci_classes, $this->library->_flame_classes);
		return $library;
	}

	public function helper($helper = []){
	 return $this->helper->make($helper);
	}

	public function model($model, $object_name = null, $params = []){
	 return $this->model->make($model, $params, $object_name);
	}

	public function database($params = '', $return = FALSE, $query_builder = NULL){
		return Database::database($params, $return, $query_builder);
	}

	public function dbutil($db = NULL, $return = FALSE){
		return Database::dbutil($db, $return);
	}

	public function dbforge($db = NULL, $return = FALSE){
		return Database::dbforge($db, $return);
	}

	public function language($langfile, $idiom = '', $return = false, $add_suffix = true, $alt_path = '')
	{
			flame()->lang->load($langfile, $idiom, $return, $add_suffix, $alt_path);
			return $this;
	}

	public function languages($languages)
	{
			foreach ($languages as $_language) {
					$this->language($_language);
			}
			return $this;
	}


	public function config($file, $use_sections = false, $fail_gracefully = false)
	{
			return flame()->config->load($file, $use_sections, $fail_gracefully);
	}

	public function driver($library, $params = NULL, $object_name = NULL){
		$driver = $this->driver->make($library, $params, $object_name);
		return $driver;
	}

	public function add_package_path($path, $view_cascade = TRUE)
	{
		return $this->package->add_path($path, $view_cascade);
	}

	public function get_package_paths($include_base = FALSE)
	{
		return $this->package->get_paths($include_base);
	}

	public function remove_package_path($path = '')
	{
		return $this->package->remove_path($path);
	}

	public function view($view, $vars = [], $return = false){
			return $this->view->make($view, $vars, $return);
	}


	public function _ci_prepare_view_vars($vars)
	{
		return $this->view->_flame_prepare_view_vars($vars);
	}


	public function file($path, $return = FALSE)
	{
		return $this->view->_flame_load($path, $return);
	}

	public function vars($vars, $val = '')
	{
		return $this->view->vars($vars, $val);
	}

	public function clear_vars()
	{
		return $this->view->clear_vars();
	}

	public function get_var($key)
	{
		return $this->view->get_var($key);
	}

	public function get_vars()
	{
		return $this->view->get_vars();
	}


	protected function &_ci_get_component($component)
	{
		return flame()->$component;
	}

	public function module($module, $params = null)
	{
		return Module::module($module, $params);
	}


	public function plugin($plugin)
	{
		$plugins = new Plugins();
		return $plugins->plugin($plugin);
	}

}
