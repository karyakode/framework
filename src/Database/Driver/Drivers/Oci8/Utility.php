<?php namespace Flame\Database\Driver\Drivers\Oci8;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Oracle Utility Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/database/
 */
class Utility extends \Flame\Database\Utility\Utility {

	/**
	 * List databases statement
	 *
	 * @var	string
	 */
	protected $_list_databases	= 'SELECT username FROM dba_users'; // Schemas are actual usernames

	/**
	 * Export
	 *
	 * @param	array	$params	Preferences
	 * @return	mixed
	 */
	protected function _backup($params = array())
	{
		// Currently unsupported
		return $this->db->display_error('db_unsupported_feature');
	}

}
