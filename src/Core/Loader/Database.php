<?php namespace Flame\Core\Loader;


class Database
{

  	public static function database($params = '', $return = FALSE, $query_builder = NULL)
  	{
  		// Do we even need to load the database class?
  		if ($return === FALSE && isset(flame()->db) && is_object(flame()->db) && ! empty(flame()->db->conn_id))
  		{
  			return FALSE;
  		}

  		if ($return === TRUE)
  		{
  			return \Flame\Database\Database::DB($params, $query_builder);
  		}

      if(flame()->has('db')) return;
  		// Load the DB class
  		flame()->set('db', \Flame\Database\Database::DB($params, $query_builder));

  	}

  	public static function dbutil($db = NULL, $return = FALSE)
  	{

  		if ( ! is_object($db) OR ! ($db instanceof \Flame\Database\DB))
  		{
  			class_exists('Flame\Database\DB', FALSE) OR self::database();
  			$db =& flame()->db;
  		}

      $class = 'Flame\Database\Driver\Drivers\\'.ucwords($this->dbdriver).'\Utility';

  		if ($return === TRUE)
  		{
  			return new $class($db);
  		}

      if(flame()->has('dbutil')) return;
  		flame()->set('dbutil', new $class($db));

  	}

  	public static function dbforge($db = NULL, $return = FALSE)
  	{
  		if ( ! is_object($db) OR ! ($db instanceof \Flame\Database\DB))
  		{
  			class_exists('Flame\Database\DB', FALSE) OR self::database();
  			$db =& flame()->db;
  		}

  		if ( ! empty($db->subdriver))
  		{
        $class = 'Flame\Database\Driver\Drivers\\'.ucwords($db->dbdriver).'\Subdrivers\\'.ucwords($db->subdriver).'\Forge';
  		}
  		else
  		{
        $class = 'Flame\Database\Driver\Drivers\\'.ucwords($db->dbdriver).'\Forge';
  		}

  		if ($return === TRUE)
  		{
  			return new $class($db);
  		}

      if(flame()->has('dbforge')) return;
  		flame()->set('dbforge', new $class($db));
  	}
}
