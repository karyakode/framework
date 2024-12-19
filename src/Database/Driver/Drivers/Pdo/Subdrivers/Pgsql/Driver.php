<?php namespace Kodhe\Database\Driver\Drivers\Pdo\Subdrivers\Pgsql;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDO PostgreSQL Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the query builder
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/database/
 */
class Driver extends \Kodhe\Database\Driver\Drivers\Pdo\Driver {

	/**
	 * Sub-driver
	 *
	 * @var	string
	 */
	public $subdriver = 'pgsql';

	/**
	 * Database schema
	 *
	 * @var	string
	 */
	public $schema = 'public';

	// --------------------------------------------------------------------

	/**
	 * ORDER BY random keyword
	 *
	 * @var	array
	 */
	protected $_random_keyword = array('RANDOM()', 'RANDOM()');

	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * Builds the DSN if not already set.
	 *
	 * @param	array	$params
	 * @return	void
	 */
	public function __construct($params)
	{
		parent::__construct($params);

		if (empty($this->dsn))
		{
			$this->dsn = 'pgsql:host='.(empty($this->hostname) ? '127.0.0.1' : $this->hostname);

			empty($this->port) OR $this->dsn .= ';port='.$this->port;
			empty($this->database) OR $this->dsn .= ';dbname='.$this->database;

			if ( ! empty($this->username))
			{
				$this->dsn .= ';user='.$this->username;
				empty($this->password) OR $this->dsn .= ';password='.$this->password;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Database connection
	 *
	 * @param	bool	$persistent
	 * @return	object
	 */
	public function db_connect($persistent = FALSE)
	{
		$this->conn_id = parent::db_connect($persistent);

		if (is_object($this->conn_id) && ! empty($this->schema))
		{
			$this->simple_query('SET search_path TO '.$this->schema.',public');
		}

		return $this->conn_id;
	}

	// --------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * @param	string	$name
	 * @return	int
	 */
	public function insert_id($name = NULL)
	{
		if ($name === NULL && version_compare($this->version(), '8.1', '>='))
		{
			$query = $this->query('SELECT LASTVAL() AS ins_id');
			$query = $query->row();
			return $query->ins_id;
		}

		return $this->conn_id->lastInsertId($name);
	}

	// --------------------------------------------------------------------

	/**
	 * Determines if a query is a "write" type.
	 *
	 * @param	string	An SQL query string
	 * @return	bool
	 */
	public function is_write_type($sql)
	{
		if (preg_match('#^(INSERT|UPDATE).*RETURNING\s.+(\,\s?.+)*$#is', $sql))
		{
			return FALSE;
		}

		return parent::is_write_type($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * "Smart" Escape String
	 *
	 * Escapes data based on type
	 *
	 * @param	string	$str
	 * @return	mixed
	 */
	public function escape($str)
	{
		if (is_bool($str))
		{
			return ($str) ? 'TRUE' : 'FALSE';
		}

		return parent::escape($str);
	}

	// --------------------------------------------------------------------

	/**
	 * ORDER BY
	 *
	 * @param	string	$orderby
	 * @param	string	$direction	ASC, DESC or RANDOM
	 * @param	bool	$escape
	 * @return	object
	 */
	public function order_by($orderby, $direction = '', $escape = NULL)
	{
		$direction = strtoupper(trim($direction));
		if ($direction === 'RANDOM')
		{
			if ( ! is_float($orderby) && ctype_digit((string) $orderby))
			{
				$orderby = ($orderby > 1)
					? (float) '0.'.$orderby
					: (float) $orderby;
			}

			if (is_float($orderby))
			{
				$this->simple_query('SET SEED '.$orderby);
			}

			$orderby = $this->_random_keyword[0];
			$direction = '';
			$escape = FALSE;
		}

		return parent::order_by($orderby, $direction, $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * Show table query
	 *
	 * Generates a platform-specific query string so that the table names can be fetched
	 *
	 * @param	bool	$prefix_limit
	 * @return	string
	 */
	protected function _list_tables($prefix_limit = FALSE)
	{
		$sql = 'SELECT "table_name" FROM "information_schema"."tables" WHERE "table_schema" = \''.$this->schema."'";

		if ($prefix_limit === TRUE && $this->dbprefix !== '')
		{
			return $sql.' AND "table_name" LIKE \''
				.$this->escape_like_str($this->dbprefix)."%' "
				.sprintf($this->_like_escape_str, $this->_like_escape_chr);
		}

		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * List column query
	 *
	 * Generates a platform-specific query string so that the column names can be fetched
	 *
	 * @param	string	$table
	 * @return	string
	 */
	protected function _list_columns($table = '')
	{
		return 'SELECT "column_name"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = '.$this->escape(strtolower($table));
	}

	// --------------------------------------------------------------------

	/**
	 * Returns an object with field data
	 *
	 * @param	string	$table
	 * @return	array
	 */
	public function field_data($table)
	{
		$sql = 'SELECT "column_name", "data_type", "character_maximum_length", "numeric_precision", "column_default"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = '.$this->escape(strtolower($table));

		if (($query = $this->query($sql)) === FALSE)
		{
			return FALSE;
		}
		$query = $query->result_object();

		$retval = array();
		for ($i = 0, $c = count($query); $i < $c; $i++)
		{
			$retval[$i]			= new \stdClass();
			$retval[$i]->name		= $query[$i]->column_name;
			$retval[$i]->type		= $query[$i]->data_type;
			$retval[$i]->max_length		= ($query[$i]->character_maximum_length > 0) ? $query[$i]->character_maximum_length : $query[$i]->numeric_precision;
			$retval[$i]->default		= $query[$i]->column_default;
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Update statement
	 *
	 * Generates a platform-specific update string from the supplied data
	 *
	 * @param	string	$table
	 * @param	array	$values
	 * @return	string
	 */
	protected function _update($table, $values)
	{
		$this->qb_limit = FALSE;
		$this->qb_orderby = array();
		return parent::_update($table, $values);
	}

	// --------------------------------------------------------------------

	/**
	 * Update_Batch statement
	 *
	 * Generates a platform-specific batch update string from the supplied data
	 *
	 * @param	string	$table	Table name
	 * @param	array	$values	Update data
	 * @param	string	$index	WHERE key
	 * @return	string
	 */
	protected function _update_batch($table, $values, $index)
	{
		$ids = array();
		foreach ($values as $key => $val)
		{
			$ids[] = $val[$index]['value'];

			foreach (array_keys($val) as $field)
			{
				if ($field !== $index)
				{
					$final[$val[$field]['field']][] = 'WHEN '.$val[$index]['value'].' THEN '.$val[$field]['value'];
				}
			}
		}

		$cases = '';
		foreach ($final as $k => $v)
		{
			$cases .= $k.' = (CASE '.$val[$index]['field']."\n"
				.implode("\n", $v)."\n"
				.'ELSE '.$k.' END), ';
		}

		$this->where($val[$index]['field'].' IN('.implode(',', $ids).')', NULL, FALSE);

		return 'UPDATE '.$table.' SET '.substr($cases, 0, -2).$this->_compile_wh('qb_where');
	}

	// --------------------------------------------------------------------

	/**
	 * Delete statement
	 *
	 * Generates a platform-specific delete string from the supplied data
	 *
	 * @param	string	$table
	 * @return	string
	 */
	protected function _delete($table)
	{
		$this->qb_limit = FALSE;
		return parent::_delete($table);
	}

	// --------------------------------------------------------------------

	/**
	 * LIMIT
	 *
	 * Generates a platform-specific LIMIT clause
	 *
	 * @param	string	$sql	SQL Query
	 * @return	string
	 */
	protected function _limit($sql)
	{
		return $sql.' LIMIT '.$this->qb_limit.($this->qb_offset ? ' OFFSET '.$this->qb_offset : '');
	}

}
