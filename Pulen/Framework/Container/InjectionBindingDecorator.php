<?php namespace Kodhe\Pulen\Framework\Container;

use Closure;

/**
 * Dependency Injection Binding Decorator
 */
class InjectionBindingDecorator implements ServiceProvider {

	/**
	 * @var ServiceProvider An object which implments ServiceProvider to be
	 *   used as a delegate if this object cannot make the requested dependency
	 */
	private $delegate;

	/**
	 * @var array An associative array of bindings
	 */
	private $bindings = array();

	public function __construct(ServiceProvider $delegate)
	{
		$this->delegate = $delegate;
	}

	public function register($name, $object)
	{
		$this->delegate->register($name, $object);
		return $this;
	}

	public function registerSingleton($name, $object)
	{
		$this->delegate->registerSingleton($name, $object);
		return $this;
	}

	public function bind($name, $object)
	{
		$this->bindings[$name] = $object;
		return $this;
	}

	public function make()
	{
		$arguments = func_get_args();
		$name = array_shift($arguments);

		if (isset($this->bindings[$name]))
		{
			$object = $this->bindings[$name];

			if ($object instanceof Closure)
			{
				array_unshift($arguments, $this);
				return call_user_func_array($object, $arguments);
			}

			return $object;
		}

		array_unshift($arguments, $name);
		array_unshift($arguments, $this);

		return call_user_func_array(
			array($this->delegate, 'make'),
			$arguments
		);
	}
}
//EOF
