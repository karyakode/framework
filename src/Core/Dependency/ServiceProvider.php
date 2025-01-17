<?php namespace Kodhe\Pulen\Core\Dependency;

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
