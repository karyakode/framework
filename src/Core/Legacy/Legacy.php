<?php namespace Kodhe\Core\Legacy;

use Kodhe\Core\Loader\Plugin\Plugin;
use Kodhe\Core\Loader\Package\Package;
use Kodhe\Core\Loader\Library\Library;
use Kodhe\Core\Loader\Model\Model;
use Kodhe\Core\Loader\Helper\Helper;
use Kodhe\Core\Loader\Driver\Driver;
use Kodhe\Core\Loader\View\View;
use Kodhe\Core\Loader\Autoloader;
use Kodhe\Core\Facade\Facade;
use Kodhe\Core\Path\Paths;
use Kodhe\Core\Dependency\DependencyResolver;
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

		$this->resolve->resolve('Kodhe\Core\Loader\Loader', []);

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

		$facade->set('blade', new \Kodhe\Core\Loader\View\Blade());

	}


	/**
	 * Override the default config
	 */
	public function overrideConfig(array $config)
	{
		$config =& load_class('Kodhe\Core\Config\Config', 'core');// new \Kodhe\Core\Config\Config();
		$config->_assign_to_config($config);
	}

	/**
	 * Override the automatic routing
	 */
	public function overrideRouting(array $routing)
	{
		$router =& load_class('Kodhe\Core\Router\Router');// new \Kodhe\Core\Router\Router();
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
		$router =& load_class('Kodhe\Core\Router\Router');//new \Kodhe\Core\Router\Router();
		$uri =& load_class('Kodhe\Core\URI\URI');
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

		class_alias('Kodhe\Controller', 'CI_Controller');

		$subclass_prefix = $GLOBALS['CFG']->item('subclass_prefix').'Controller';

		if (file_exists(resolve_path(APPPATH,'core').'/'.$subclass_prefix.'.php') && class_exists(kodhe('setup')->get('App:namespace').'\Core\\'.$subclass_prefix, FALSE) === FALSE)
		{
			require resolve_path(APPPATH,'core').'/'.$subclass_prefix.'.php';
		}

	}

	/**
	 * Attempt to load the requested controller
	 */
	public function loadController($routing)
	{
		$modules = new \Kodhe\Core\Module\Module();

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
			if (method_exists('Kodhe\Modules\NotFound\Controllers\NotFound', 'index')) {
				return call_user_func_array(['Kodhe\Modules\NotFound\Controllers\NotFound', 'index'], array());
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
		$BM = load_class('Kodhe\Core\Benchmark\Benchmark', 'core');
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
			'BM'	=>'Kodhe\Core\Benchmark\Benchmark',
			'CFG'	=>'Kodhe\Core\Config\Config',
			'UNI'	=>'Kodhe\Core\Utf8\Utf8',
			'URI'	=>'Kodhe\Core\URI\URI',
			'RTR'	=>'Kodhe\Core\Router\Router',
			'OUT'	=>'Kodhe\Core\Output\Output',
			'SEC'	=>'Kodhe\Core\Security\Security',
			'IN'	=>'Kodhe\Core\Input\Input',
			'LANG'	=>'Kodhe\Core\Lang\Lang',
			'MOD'	=>'Kodhe\Core\Module\Module',
		);

		foreach ($ci_core as $key => $class) {
			$GLOBALS[$key]   =& load_class($class, 'core');
		}

		$GLOBALS['kodhe'] = $this->getFacade();
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
				kodhe()->set(strtolower($objectname), new $name);
			}
		}


	}
	/**
	 * Alias core classes that were renamed from CI_ to FL_
	 */
	protected function aliasClasses()
	{

		$ci_core = array(
			'CI_Benchmark'	=>'Kodhe\Core\Benchmark\Benchmark',
			'CI_Config'	=>'Kodhe\Core\Config\Config',
			'CI_Utf8'	=>'Kodhe\Core\Utf8\Utf8',
			'CI_URI'	=>'Kodhe\Core\URI\URI',
			'CI_Router'	=>'Kodhe\Core\Router\Router',
			'CI_Output'	=>'Kodhe\Core\Output\Output',
			'CI_Security'	=>'Kodhe\Core\Security\Security',
			'CI_Input'	=>'Kodhe\Core\Input\Input',
			'CI_Lang'	=>'Kodhe\Core\Lang\Lang',
			'CI_Model'	=>'Kodhe\Core\Model\Legacy',
			'CI_Module'	=>'Kodhe\Core\Module\Module',

			'Model'	=>'Kodhe\Core\Model\Model',

		);

		if(class_exists('Kodhe\Core\Loader\Loader')) {
			class_alias('Kodhe\Core\Loader\Loader', 'CI_Loader');
		}

		foreach ($ci_core as $alias => $class) {
			if(class_exists($class)) {
				if(class_exists($alias) == FALSE) class_alias($class, $alias);
			}
		}

	$ci_libraries = array(
		'CI_Calendar'=>'Kodhe\Libraries\Calendar\Calendar',
		'CI_Cart'=>'Kodhe\Libraries\Cart\Cart',
		'CI_Driver_Library'=>'Kodhe\Libraries\Driver\Library',
		'CI_Driver'=>'Kodhe\Libraries\Driver\Driver\Driver',
		'CI_Email'=>'Kodhe\Libraries\Email\Email',
		'CI_Encrypt'=>'Kodhe\Libraries\Encrypt\Encrypt',
		'CI_Encryption'=>'Kodhe\Libraries\Encryption\Encryption',
		'CI_Form_validation'=>'Kodhe\Libraries\FormValidation\FormValidation',
		'CI_Ftp'=>'Kodhe\Libraries\Ftp\Ftp',
		'CI_Image_lib'=>'Kodhe\Libraries\Image_lib\Image_lib',
		'CI_Javascript'=>'Kodhe\Libraries\Javascript\Javascript',
		'CI_Log'=>'Kodhe\Libraries\Log\Log',
		'CI_Migration'=>'Kodhe\Libraries\Migration\Migration',
		'CI_Pagination'=>'Kodhe\Libraries\Pagination\Pagination',
		'CI_Parser'=>'Kodhe\Libraries\Parser\Parser',
		'CI_Profiler'=>'Kodhe\Libraries\Profiler\Profiler',
		'CI_Table'=>'Kodhe\Libraries\Table\Table',
		'CI_Trackback'=>'Kodhe\Libraries\Trackback\Trackback',
		'CI_Typography'=>'Kodhe\Libraries\Typography\Typography',
		'CI_Unit_test'=>'Kodhe\Libraries\UnitTest\UnitTest',
		'CI_Upload'=>'Kodhe\Libraries\Upload\Upload',
		'CI_User_agent'=>'Kodhe\Libraries\UserAgent\UserAgent',
		'CI_Xmlrpc'=>'Kodhe\Libraries\Xmlrpc\Xmlrpc',
		'CI_Zip'=>'Kodhe\Libraries\Zip\Zip',
	);

	foreach ($ci_libraries as $alias => $class) {
		if(class_exists($class)) {
			if(class_exists($alias) == FALSE) class_alias($class, $alias);
		}
	}

	}
}

// EOF
