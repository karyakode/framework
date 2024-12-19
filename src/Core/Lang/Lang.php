<?php namespace Flame\Core\Lang;
defined('BASEPATH') OR exit('No direct script access allowed');

use Flame\Core\Module\Module;

class Lang {

	public $language =	array();

	public $is_loaded =	array();

	public function __construct()
	{
		log_message('info', 'Language Class Initialized');
	}

	public function load($langfile, $lang = '', $return = false, $add_suffix = true, $alt_path = '', $_module = '')
	{
			if (is_array($langfile)) {
					foreach ($langfile as $_lang) {
							$this->load($_lang);
					}
					return $this->language;
			}

			$deft_lang = flame()->config->item('language');
			$idiom = ($lang === '') ? $deft_lang : $lang;

			if (in_array($langfile.'_lang'.'.php', $this->is_loaded, true)) {
					return $this->language;
			}

			// Backward function
			// Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
			if (version_compare(phpversion(), '7.1', '<')) {
					// php version isn't high enough
					list($path, $_langfile) = Module::find($langfile.'_lang', 'language/'.$idiom.'/');
			} else {
					[$path, $_langfile] = Module::find($langfile.'_lang', 'language/'.$idiom.'/');
			}

			if ($path === false) {
					if ($lang = $this->load_legacy($langfile, $lang, $return, $add_suffix, $alt_path)) {
							return $lang;
					}
			} else {
					if ($lang = Module::load_file($_langfile, $path, 'lang')) {
							if ($return) {
									return $lang;
							}
							$this->language = array_merge($this->language, $lang);
							$this->is_loaded[] = $langfile.'_lang'.'.php';
							unset($lang);
					}
			}

			return $this->language;
	}

	public function load_legacy($langfile, $idiom = '', $return = FALSE, $add_suffix = TRUE, $alt_path = '')
	{
		if (is_array($langfile))
		{
			foreach ($langfile as $value)
			{
				$this->load_legacy($value, $idiom, $return, $add_suffix, $alt_path);
			}

			return;
		}

		$langfile = str_replace('.php', '', $langfile);

		if ($add_suffix === TRUE)
		{
			$langfile = preg_replace('/_lang$/', '', $langfile).'_lang';
		}

		$langfile .= '.php';

		if (empty($idiom) OR ! preg_match('/^[a-z_-]+$/i', $idiom))
		{
			$config =& get_config();
			$idiom = empty($config['language']) ? 'english' : $config['language'];
		}

		if ($return === FALSE && isset($this->is_loaded[$langfile]) && $this->is_loaded[$langfile] === $idiom)
		{
			return;
		}

		// Load the base file, so any others found can override it
		$basepath = resolve_path(BASEPATH,'language').'/'.$idiom.'/'.$langfile;
		if (($found = file_exists($basepath)) === TRUE)
		{
			include($basepath);
		}

		// Do we have an alternative path to look in?
		if ($alt_path !== '')
		{
			$alt_path = resolve_path($alt_path,'language').'/'.$idiom.'/'.$langfile;
			if (file_exists($alt_path))
			{
				include($alt_path);
				$found = TRUE;
			}
		}
		else
		{
			foreach (flame()->load->get_package_paths(TRUE) as $package_path)
			{
				$package_path = resolve_path($package_path,'language').'/'.$idiom.'/'.$langfile;
				if ($basepath !== $package_path && file_exists($package_path))
				{
					include($package_path);
					$found = TRUE;
					break;
				}
			}
		}

		if ($found !== TRUE)
		{
			show_error('Unable to load the requested language file: language/'.$idiom.'/'.$langfile);
		}

		if ( ! isset($lang) OR ! is_array($lang))
		{
			log_message('error', 'Language file contains no data: language/'.$idiom.'/'.$langfile);

			if ($return === TRUE)
			{
				return array();
			}
			return;
		}

		if ($return === TRUE)
		{
			return $lang;
		}

		$this->is_loaded[$langfile] = $idiom;
		$this->language = array_merge($this->language, $lang);

		log_message('info', 'Language file loaded: language/'.$idiom.'/'.$langfile);
		return TRUE;
	}

	public function line($line, $log_errors = TRUE)
	{
		$value = isset($this->language[$line]) ? $this->language[$line] : FALSE;

		// Because killer robots like unicorns!
		if ($value === FALSE && $log_errors === TRUE)
		{
			log_message('error', 'Could not find the language line "'.$line.'"');
		}

		return $value;
	}

}
