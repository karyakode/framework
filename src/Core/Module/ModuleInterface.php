<?php namespace Flame\Core\Module;

interface ModuleInterface
{

  public static function module($module, $params = null);
  public static function run($module);
  public static function load($module);
  public static function load_file($file, $path, $type = 'other', $result = true);
  public static function find($file, $module, $base);
  public static function parse_routes($module, $uri);
  public static function getLocations();
  public static function setLocations($locations = []);

}
