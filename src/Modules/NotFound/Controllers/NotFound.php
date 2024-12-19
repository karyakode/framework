<?php namespace Kodhe\Modules\NotFound\Controllers;
use Kodhe\Controller;
class NotFound extends Controller {

	public static function index()
	{
		show_404("Unable to load the requested controller.");
	}
}
