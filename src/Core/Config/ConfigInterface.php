<?php namespace Flame\Core\Config;

interface ConfigInterface
{
  public function load($file = '', $use_sections = false, $fail_gracefully = false, $_module = '');
  public function item($item, $index = '');
  public function slash_item($item);
  public function site_url($uri = '', $protocol = NULL);
  public function base_url($uri = '', $protocol = NULL);
  public function system_url();
  public function set_item($item, $value);
  public function _assign_to_config($items = array());

}
