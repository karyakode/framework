<?php namespace Kodhe\Pulen\Framework\Container;

use Closure;

/**
 * Service Provider Interface
 */
interface ServiceProvider {

	public function register($name, $object);
	public function bind($name, $object);
	public function registerSingleton($name, $object);
	public function make();

}
