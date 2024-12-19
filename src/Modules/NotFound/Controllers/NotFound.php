<?php namespace Flame\Modules\NotFound\Controllers;
use Flame\Controller;
class NotFound extends Controller {

	public static function index()
	{
		show_404("Unable to load the requested controller.");
	}
}
