<?php namespace Flame\Database\Driver\Drivers\Mssql;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MS SQL Utility Class
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
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
	protected $_list_databases	= 'EXEC sp_helpdb'; // Can also be: EXEC sp_databases

	/**
	 * OPTIMIZE TABLE statement
	 *
	 * @var	string
	 */
	protected $_optimize_table	= 'ALTER INDEX all ON %s REORGANIZE';

	/**
	 * Export
	 *
	 * @param	array	$params	Preferences
	 * @return	bool
	 */
	protected function _backup($params = array())
	{
		// Currently unsupported
		return $this->db->display_error('db_unsupported_feature');
	}

}
