<?php namespace Kodhe\Pulen\Legacy;

use Kodhe\Pulen\Framework\Loader\Plugin\Plugin;
use Kodhe\Pulen\Framework\Loader\Package\Package;
use Kodhe\Pulen\Framework\Loader\Library\Library;
use Kodhe\Pulen\Framework\Loader\Model\Model;
use Kodhe\Pulen\Framework\Loader\Helper\Helper;
use Kodhe\Pulen\Framework\Loader\Driver\Driver;
use Kodhe\Pulen\Framework\Loader\View\View;
use Kodhe\Pulen\Framework\Loader\Autoloader;
use Kodhe\Pulen\Framework\Support\Facade\Facade;
use Kodhe\Pulen\Framework\Support\Path\Paths;
use Kodhe\Pulen\Framework\Container\DependencyResolver;
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

		$this->resolve->resolve('Kodhe\Pulen\Framework\Loader\Loader', []);

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


		$facade->set('blade', new \Kodhe\Pulen\Framework\Loader\View\Blade());

	}


	/**
	 * Override the default config
	 */
	public function overrideConfig(array $config)
	{
		$config =& load_class('Kodhe\Pulen\Framework\Config\Config', 'core');// new \Kodhe\Pulen\Framework\Config\Config();
		$config->_assign_to_config($config);
	}

	/**
	 * Override the automatic routing
	 */
	public function overrideRouting(array $routing)
	{
		$router =& load_class('Kodhe\Pulen\Framework\Http\Router\Router');// new \Kodhe\Pulen\Framework\Http\Router\Router();
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
		$router =& load_class('Kodhe\Pulen\Framework\Http\Router\Router');//new \Kodhe\Pulen\Framework\Http\Router\Router();
		$uri =& load_class('Kodhe\Pulen\Framework\Http\Uri\URI');
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

		class_alias('Kodhe\Pulen\Controller', 'CI_Controller');

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
		$modules = new \Kodhe\Pulen\Framework\Modules\Module();

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
			if (method_exists('Kodhe\Pulen\Modules\NotFound\Controllers\NotFound', 'index')) {
				return call_user_func_array(['Kodhe\Pulen\Modules\NotFound\Controllers\NotFound', 'index'], array());
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
		$BM = load_class('Kodhe\Pulen\Framework\Support\Benchmark\Benchmark', 'core');
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
			'BM'	=>'Kodhe\Pulen\Framework\Support\Benchmark\Benchmark',
			'CFG'	=>'Kodhe\Pulen\Framework\Config\Config',
			'UNI'	=>'Kodhe\Pulen\Framework\Support\Utf8\Utf8',
			'URI'	=>'Kodhe\Pulen\Framework\Http\Uri\URI',
			'RTR'	=>'Kodhe\Pulen\Framework\Http\Router\Router',
			'OUT'	=>'Kodhe\Pulen\Framework\Http\Output\Output',
			'SEC'	=>'Kodhe\Pulen\Framework\Security\Security',
			'IN'	=>'Kodhe\Pulen\Framework\Http\Input\Input',
			'LANG'	=>'Kodhe\Pulen\Resources\Lang\Lang',
			'MOD'	=>'Kodhe\Pulen\Framework\Modules\Module',
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
				$GLOBALS['kodhe']->set(strtolower($objectname), new $name);
			}
		}


	}
	/**
	 * Alias core classes that were renamed from CI_ to FL_
	 */
	protected function aliasClasses()
	{

		$ci_core = array(
			'CI_Benchmark'	=>'Kodhe\Pulen\Framework\Support\Benchmark\Benchmark',
			'CI_Config'	=>'Kodhe\Pulen\Framework\Config\Config',
			'CI_Utf8'	=>'Kodhe\Pulen\Framework\Support\Utf8\Utf8',
			'CI_URI'	=>'Kodhe\Pulen\Framework\Http\Uri\URI',
			'CI_Router'	=>'Kodhe\Pulen\Framework\Http\Router\Router',
			'CI_Output'	=>'Kodhe\Pulen\Framework\Http\Output\Output',
			'CI_Security'	=>'Kodhe\Pulen\Framework\Security\Security',
			'CI_Input'	=>'Kodhe\Pulen\Framework\Http\Input\Input',
			'CI_Lang'	=>'Kodhe\Pulen\Resources\Lang\Lang',
			'CI_Model'	=>'Kodhe\Pulen\Framework\Model\Legacy',
			'CI_Module'	=>'Kodhe\Pulen\Framework\Modules\Module',

			'Model'	=>'Kodhe\Pulen\Framework\Model\Model',

		);

		if(class_exists('Kodhe\Pulen\Framework\Loader\Loader')) {
			class_alias('Kodhe\Pulen\Framework\Loader\Loader', 'CI_Loader');
		}

		foreach ($ci_core as $alias => $class) {
			if(class_exists($class)) {
				if(class_exists($alias) == FALSE) class_alias($class, $alias);
			}
		}

	$ci_libraries = array(
		'CI_Calendar'=>'Kodhe\Pulen\Libraries\Calendar\Calendar',
		'CI_Cart'=>'Kodhe\Pulen\Libraries\Cart\Cart',
		'CI_Driver_Library'=>'Kodhe\Pulen\Libraries\Driver\Library',
		'CI_Driver'=>'Kodhe\Pulen\Libraries\Driver\Driver\Driver',
		'CI_Email'=>'Kodhe\Pulen\Libraries\Email\Email',
		'CI_Encrypt'=>'Kodhe\Pulen\Libraries\Encrypt\Encrypt',
		'CI_Encryption'=>'Kodhe\Pulen\Libraries\Encryption\Encryption',
		'CI_Form_validation'=>'Kodhe\Pulen\Libraries\FormValidation\FormValidation',
		'CI_Ftp'=>'Kodhe\Pulen\Libraries\Ftp\Ftp',
		'CI_Image_lib'=>'Kodhe\Pulen\Libraries\Image_lib\Image_lib',
		'CI_Javascript'=>'Kodhe\Pulen\Libraries\Javascript\Javascript',
		'CI_Log'=>'Kodhe\Pulen\Libraries\Log\Log',
		'CI_Migration'=>'Kodhe\Pulen\Libraries\Migration\Migration',
		'CI_Pagination'=>'Kodhe\Pulen\Libraries\Pagination\Pagination',
		'CI_Parser'=>'Kodhe\Pulen\Libraries\Parser\Parser',
		'CI_Profiler'=>'Kodhe\Pulen\Libraries\Profiler\Profiler',
		'CI_Table'=>'Kodhe\Pulen\Libraries\Table\Table',
		'CI_Trackback'=>'Kodhe\Pulen\Libraries\Trackback\Trackback',
		'CI_Typography'=>'Kodhe\Pulen\Libraries\Typography\Typography',
		'CI_Unit_test'=>'Kodhe\Pulen\Libraries\UnitTest\UnitTest',
		'CI_Upload'=>'Kodhe\Pulen\Libraries\Upload\Upload',
		'CI_User_agent'=>'Kodhe\Pulen\Libraries\UserAgent\UserAgent',
		'CI_Xmlrpc'=>'Kodhe\Pulen\Libraries\Xmlrpc\Xmlrpc',
		'CI_Zip'=>'Kodhe\Pulen\Libraries\Zip\Zip',
	);

	foreach ($ci_libraries as $alias => $class) {
		if(class_exists($class)) {
			if(class_exists($alias) == FALSE) class_alias($class, $alias);
		}
	}

	}
}

// EOF
