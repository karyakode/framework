<?php namespace Flame\Core\Legacy;

use Flame\Core\Loader\Plugin\Plugin;
use Flame\Core\Loader\Package\Package;
use Flame\Core\Loader\Library\Library;
use Flame\Core\Loader\Model\Model;
use Flame\Core\Loader\Helper\Helper;
use Flame\Core\Loader\Driver\Driver;
use Flame\Core\Loader\View\View;
use Flame\Core\Loader\Autoloader;
use Flame\Core\Facade\Facade;
use Flame\Core\Path\Paths;
use Flame\Core\Dependency\DependencyResolver;
class Legacy {

	protected $router_ready = FALSE;
	protected DependencyResolver $resolve;

	function __construct(){
		$this->resolve =  new DependencyResolver();

	}
	/**
	 * Boot the legacy application
	 */
	public function boot()
	{
		$this->startBenchmark();
		$this->exposeGlobals();
		$this->aliasClasses();
		$this->overrideRoutingConfig();
	}

	/**
	 * Get the superobject facade
	 */
	public function getFacade()
	{
		if ( ! isset($this->facade))
		{
			$this->facade = new Facade();
			$this->setFacade($this->facade);
		}


		return $this->facade;
	}


	private function setFacade(Facade $facade){

		$this->resolve->resolve('Flame\Core\Loader\Loader', []);

		foreach (is_loaded() as $var => $class)
		{
			$var = ($var == 'loader') ? 'load' : $var;
			$facade->set($var, load_class($class, 'core'));
		}

		foreach ($this->resolve->classLoaded as $var => $class)
		{
			$var = ($var == 'loader') ? 'load' : $var;
			$facade->set($var, $class);
		}


	}


	/**
	 * Override the default config
	 */
	public function overrideConfig(array $config)
	{
		$config =& load_class('Flame\Core\Config\Config', 'core');// new \Flame\Core\Config\Config();
		$config->_assign_to_config($config);
	}

	/**
	 * Override the automatic routing
	 */
	public function overrideRouting(array $routing)
	{
		$router =& load_class('Flame\Core\Router\Router');// new \Flame\Core\Router\Router();
		if ( ! $this->router_ready)
		{
			$router->_set_routing();
			$this->router_ready = TRUE;
		}

		$router->_set_overrides($routing);
	}

	/**
	 * Run the router and get back the requested path, method, and
	 * additional segments
	 */
	public function getRouting()
	{
		$router =& load_class('Flame\Core\Router\Router');//new \Flame\Core\Router\Router();
		$uri =& load_class('Flame\Core\URI\URI');
		if ( ! $this->router_ready)
		{
			$router->_set_routing();
			$this->router_ready = TRUE;
		}

		$directory = $router->fetch_directory();
		$class     = ucfirst($router->fetch_class());
		$method    = $router->fetch_method();
		$segments  = array_slice($uri->rsegment_array(), 2);

		return compact('directory', 'class', 'method', 'segments');
	}

	/**
	 * Include the controller base classes
	 */
	public function includeBaseController()
	{

		class_alias('Flame\Controller', 'CI_Controller');

		$subclass_prefix = $GLOBALS['CFG']->item('subclass_prefix').'Controller';

		if (file_exists(resolve_path(APPPATH,'core').'/'.$subclass_prefix.'.php') && class_exists(flame('setup')->get('App:namespace').'\Core\\'.$subclass_prefix, FALSE) === FALSE)
		{
			require resolve_path(APPPATH,'core').'/'.$subclass_prefix.'.php';
		}

	}

	/**
	 * Attempt to load the requested controller
	 */
	public function loadController($routing)
	{
		$modules = new \Flame\Core\Module\Module();

		foreach ($modules::getLocations() as $location => $offset) {
			$path = $location.'/'.str_replace($offset,'',$routing['directory']).$routing['class'].'.php';

			if (file_exists($path))
			{
				require $path;
				return;
			}

		}



		if ( ! file_exists(resolve_path(APPPATH,'controllers').'/'.$routing['directory'].$routing['class'].'.php'))
		{
			if (method_exists('Flame\Modules\NotFound\Controllers\NotFound', 'index')) {
				return call_user_func_array(['Flame\Modules\NotFound\Controllers\NotFound', 'index'], array());
			}
		}

		require resolve_path(APPPATH,'controllers').'/'.$routing['directory'].$routing['class'].'.php';


	}

	/**
	 * Returns a list of valid
	 */
	public function isLegacyRouted($routing)
	{

		return TRUE;
	}


	/**
	 * Set a benchmark point
	 */
	public function markBenchmark($str)
	{
		$BM = load_class('Flame\Core\Benchmark\Benchmark', 'core');
		$BM->mark($str);
	}

	/**
	 * Validate the request
	 *
	 * Ensures that we're not going to call something that doesn't
	 * exist or was marked as pseudo-private.
	 */
	public function validateRequest($routing)
	{
		$class = $routing['class'];
		$method = $routing['method'];

		if (class_exists($class) && strncmp($method, '_', 1) != 0)
		{
			$controller_methods = array_map(
				'strtolower', get_class_methods($class)
			);

			// if there's a _remap method we'll call it, regardless of
			// the method they requested
			if (in_array('_remap', $controller_methods))
			{
				$routing['method'] = '_remap';
				$routing['segments'] = array($method, $routing['segments']);

				return $routing;
			}

			if (in_array(strtolower($method), $controller_methods)
				|| method_exists($class, '__call'))
			{
				return $routing;
			}
		}

		return FALSE;
	}

	/**
	 * Set EE's default routing config
	 */
	protected function overrideRoutingConfig()
	{
		$routing_config = array(
			'directory_trigger'    => 'D',
			'controller_trigger'   => 'C',
			'function_trigger'     => 'M',
			'enable_query_strings' => FALSE
		);

		if (defined('REQ') && REQ == 'CP')
		{
			$routing_config['enable_query_strings'] = TRUE;
		}

		$this->overrideConfig($routing_config);
	}

	/**
	 * Start the benchmark library early
	 */
	protected function startBenchmark()
	{
		$this->markBenchmark('total_execution_time_start');
	}

	/**
	 * Expose silly globals
	 */
	protected function exposeGlobals()
	{
		// in php 5.4 $GLOBALS is a JIT variable, so this is
		// technically a performance hit. Yet another reason
		// to ditch it all very soon.

		$ci_core = array(
			'BM'	=>'Flame\Core\Benchmark\Benchmark',
			'CFG'	=>'Flame\Core\Config\Config',
			'UNI'	=>'Flame\Core\Utf8\Utf8',
			'URI'	=>'Flame\Core\URI\URI',
			'RTR'	=>'Flame\Core\Router\Router',
			'OUT'	=>'Flame\Core\Output\Output',
			'SEC'	=>'Flame\Core\Security\Security',
			'IN'	=>'Flame\Core\Input\Input',
			'LANG'	=>'Flame\Core\Lang\Lang',
			'MOD'	=>'Flame\Core\Module\Module',
		);

		foreach ($ci_core as $key => $class) {
			$GLOBALS[$key]   =& load_class($class, 'core');
		}

		$GLOBALS['flame'] = $this->getFacade();
	}

	public function overwriteCore()
	{

		$ci_core = array(
			'benchmark'	=>'Benchmark',
			'config'	=>'Config',
			'utf8'	=>'Utf8',
			'uri'	=>'URI',
			'router'	=>'Router',
			//'output'	=>'Output',
			'security'	=>'Security',
			'input'	=>'Input',
			'lang'	=>'Lang',
			'load'	=>'Loader',
		);

		$subclass_prefix = $GLOBALS['CFG']->item('subclass_prefix');

		foreach ($ci_core as $objectname => $class) {
			//$objectname = str_replace('CI_','',$name);
			$filename = find_class_file($class, 'core', []);
			//echo $name = 'App\Core\\'.$subclass_prefix.$name;
			if(class_exists('App\Core\\'.$subclass_prefix.$class)) {
				$name = 'App\Core\\'.$subclass_prefix.$class;
			} elseif(class_exists('App\Core\\'.$class)) {
				$name = 'App\Core\\'.$class;
			}else {
				$name = $subclass_prefix.$class;
			}

			if(class_exists($name)) {
				flame()->set(strtolower($objectname), new $name);
			}
		}


	}
	/**
	 * Alias core classes that were renamed from CI_ to FL_
	 */
	protected function aliasClasses()
	{

		$ci_core = array(
			'CI_Benchmark'	=>'Flame\Core\Benchmark\Benchmark',
			'CI_Config'	=>'Flame\Core\Config\Config',
			'CI_Utf8'	=>'Flame\Core\Utf8\Utf8',
			'CI_URI'	=>'Flame\Core\URI\URI',
			'CI_Router'	=>'Flame\Core\Router\Router',
			'CI_Output'	=>'Flame\Core\Output\Output',
			'CI_Security'	=>'Flame\Core\Security\Security',
			'CI_Input'	=>'Flame\Core\Input\Input',
			'CI_Lang'	=>'Flame\Core\Lang\Lang',
			'CI_Model'	=>'Flame\Core\Model\Legacy',
			'CI_Module'	=>'Flame\Core\Module\Module',

			'Model'	=>'Flame\Core\Model\Model',

		);

		if(class_exists('Flame\Core\Loader\Loader')) {
			class_alias('Flame\Core\Loader\Loader', 'CI_Loader');
		}

		foreach ($ci_core as $alias => $class) {
			if(class_exists($class)) {
				if(class_exists($alias) == FALSE) class_alias($class, $alias);
			}
		}

	$ci_libraries = array(
		'CI_Calendar'=>'Flame\Libraries\Calendar\Calendar',
		'CI_Cart'=>'Flame\Libraries\Cart\Cart',
		'CI_Driver_Library'=>'Flame\Libraries\Driver\Library',
		'CI_Driver'=>'Flame\Libraries\Driver\Driver\Driver',
		'CI_Email'=>'Flame\Libraries\Email\Email',
		'CI_Encrypt'=>'Flame\Libraries\Encrypt\Encrypt',
		'CI_Encryption'=>'Flame\Libraries\Encryption\Encryption',
		'CI_Form_validation'=>'Flame\Libraries\FormValidation\FormValidation',
		'CI_Ftp'=>'Flame\Libraries\Ftp\Ftp',
		'CI_Image_lib'=>'Flame\Libraries\Image_lib\Image_lib',
		'CI_Javascript'=>'Flame\Libraries\Javascript\Javascript',
		'CI_Log'=>'Flame\Libraries\Log\Log',
		'CI_Migration'=>'Flame\Libraries\Migration\Migration',
		'CI_Pagination'=>'Flame\Libraries\Pagination\Pagination',
		'CI_Parser'=>'Flame\Libraries\Parser\Parser',
		'CI_Profiler'=>'Flame\Libraries\Profiler\Profiler',
		'CI_Table'=>'Flame\Libraries\Table\Table',
		'CI_Trackback'=>'Flame\Libraries\Trackback\Trackback',
		'CI_Typography'=>'Flame\Libraries\Typography\Typography',
		'CI_Unit_test'=>'Flame\Libraries\UnitTest\UnitTest',
		'CI_Upload'=>'Flame\Libraries\Upload\Upload',
		'CI_User_agent'=>'Flame\Libraries\UserAgent\UserAgent',
		'CI_Xmlrpc'=>'Flame\Libraries\Xmlrpc\Xmlrpc',
		'CI_Zip'=>'Flame\Libraries\Zip\Zip',
	);

	foreach ($ci_libraries as $alias => $class) {
		if(class_exists($class)) {
			if(class_exists($alias) == FALSE) class_alias($class, $alias);
		}
	}

	}
}

// EOF
