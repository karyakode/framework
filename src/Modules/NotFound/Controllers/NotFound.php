<?php namespace Kodhe\Pulen\Modules\NotFound\Controllers;
use Kodhe\Pulen\Controller;
class NotFound extends Controller {

	public static function index()
	{
		show_404("Unable to load the requested controller.");
	}
}
