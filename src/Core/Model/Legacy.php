<?php namespace Flame\Core\Model;
defined('BASEPATH') OR exit('No direct script access allowed');

class Legacy {

	public function __construct() {}

	public function __get($key)
	{
		return flame()->get($key);
	}

}
