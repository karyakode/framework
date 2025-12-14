<?php namespace Kodhe\Pulen\Framework\Model;

class Legacy {

	public function __construct() {}

	public function __get($key)
	{
		return kodhe()->get($key);
	}

}
