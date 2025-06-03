<?php namespace Kodhe\Pulen\Framework\Support\Path;

define('VENDORPATH', dirname(dirname(__DIR__)));

class Paths {

	public static $viewPaths =	[
		VIEWPATH	=> TRUE
	];
	public static $libraryPaths =	[
		APPPATH
	];
	public static $modelPaths =	[
		APPPATH
	];
	public static $helperPaths =	[
		APPPATH,
		VENDORPATH
	];

}
