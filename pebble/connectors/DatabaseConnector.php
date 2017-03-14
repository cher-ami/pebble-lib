<?php

namespace pebble\connectors;

use pebble\core\PebbleApp;
use Exception;
use PDO;

class DatabaseConnector
{
	// ------------------------------------------------------------------------- SINGLETON

	/**
	 * @var DatabaseConnector
	 */
	static protected $__instance;

	/**
	 * @return DatabaseConnector
	 */
	static function getInstance ()
	{
		if ( is_null(self::$__instance) )
		{
			self::$__instance = new DatabaseConnector();
		}
		return self::$__instance;
	}


	// ------------------------------------------------------------------------- CONNECTION

	/**
	 * Stored ref to the PDO connection
	 * @var PDO
	 */
	protected $_connection;

	/**
	 * Returns the PDO instance used to handle database operations.
	 * @return PDO  The PDO instance used to handle database operations.
	 */
	public function getConnection()
	{
		return $this->_connection;
	}

	/**
	 * Connect to database with specific params.
	 * If call to this method is omited, it'll be automatically called before any query with connect info from config.
	 * If already connect, will be skiped.
	 * @param $pConfigParams : If not specified, will use connect info from config.
	 * @throws Exception
	 */
	public function connect ($pConfigParams = null)
	{
		// If we already have a connection, skip this
		if ( !is_null($this->_connection) ) return;

		// Get connect info from config if there is nothing in parameters
		if ( is_null($pConfigParams) )
		{
			// Get config
			$pConfigParams = PebbleApp::getInstance()->getConfig('database');
		}

		// Catch error to avoid credentials to be shown in debug message
		try
		{
			// Connect to database
			$this->_connection = new PDO('mysql:host='.$pConfigParams['host'].';charset=utf8mb4', $pConfigParams['user'], $pConfigParams['password']);
			$this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		}
		catch (Exception $e)
		{
			// Redispatch custom exception
			throw new Exception('DatabaseConnector.connect // Unable to connect. ('.$e->getCode().' - '.$e->getMessage().')');
		}

		// Catch error to avoid credentials to be shown in debug message
		try
		{
			// Initialiser la base de données
			$this->initDatabase();
		}
		catch (Exception $e)
		{
			// Redispatch custom exception
			throw new Exception('DatabaseConnector.connect // Unable to init database. ('.$e->getCode().' - '.$e->getMessage().')');
		}

		// Sélectionner la base de données
		$this->selectDB( $pConfigParams['dbname'] );
	}


	// ------------------------------------------------------------------------- LOGS

	/**
	 * All logged queries
	 * @var array
	 */
	protected $_queryLog = [];

	/**
	 * All logged queries
	 * @return array
	 */
	public function getQueryLog () { return $this->_queryLog; }


	// ------------------------------------------------------------------------- HELPERS

	/**
	 * Prepare query and log it.
	 * @param $pQuery : The query to prepare and log.
	 * @return \PDOStatement
	 */
	public function prepareQuery ($pQuery)
	{
		// Log
		$this->_queryLog[] = $pQuery;

		// Prepare and return
		return $this->_connection->prepare( $pQuery );
	}

	/**
	 * Select database when connected.
	 * @param string $pDBName Name of database to connect to
	 */
	public function selectDB ($pDBName)
	{
		// Format query
		$query = "use `$pDBName`";

		// Log query
		$this->_queryLog[] = $query;

		// Select DB
		$this->_connection->query( $query );
	}

	/**
	 * Init database and create tables from database-schema config.
	 * Will do nothing if database already exists
	 */
	public function initDatabase ()
	{
		// Get database schema config
		$schemaConfig = PebbleApp::getInstance()->getConfig('database-schema');
		$databaseConfig = PebbleApp::getInstance()->getConfig('database');

		// If we have a config
		if (isset($schemaConfig) && isset($schemaConfig['tables']))
		{
			// Get db name from schema
			$dbname = $databaseConfig['dbname'];

			// Check if DB exists
			$statement = $this->prepareQuery('SHOW DATABASES LIKE :dbname');
			$statement->execute([
				':dbname' => $dbname
			]);
			$dbExists = (count($statement->fetchAll()) > 0);

			// If this DB doesn't exists
			if (!$dbExists)
			{
				// Create empty DB
				$this->_connection->exec("create database if not exists `$dbname`");

				// Sélectionner la base
				$this->_connection->query("use `$dbname`");

				// Créer les tables
				foreach ($schemaConfig['tables'] as $table)
				{
					$this->_connection->query( $table );
				}
			}
		}
	}

	/**
	 * Fetch from query.
	 * Will be logged.
	 * @param string $pQuery : The query to prepare and execute.
	 * @param array $pData : Data to send. Associated array with ':' prefixed keys
	 * @param int $pFetchType : @see PDOStatement::fetchAll()
	 * @return array Data from executed query, fetched as $pFecthType
	 */
	public function fetch ($pQuery = '', $pData = [], $pFetchType = PDO::FETCH_ASSOC)
	{
		$statement = $this->prepareQuery( $pQuery );
		$statement->execute( $pData );
		return $statement->fetchAll( $pFetchType );
	}



	/**
	 * Saves the given $pData to the given database $pTable.
	 * Will be logged.
	 * @param string $pTableName The name of the table to insert into.
	 * @param array $pData An associative array representing the data to save. Keys represent the table columns.
	 * @return bool `true` if data was saved `false` otherwise.
	 */
	public function insertInto ($pTableName, $pData = [])
	{
		// Connect through PDO
		$this->connect();

		// We need to prefix the keys with ":" to use them in as PDO prepared statement placeholders.
		// But we also need the unprefixed version to specify in which column the data is to be inserted.
		$columns = array();
		foreach ($pData as $column => $data)
		{
			$columns[] = $column;
			$pData[":{$column}"] = $data;
			unset($pData[$column]);
		}

		// Ensure both arrays are sorted in the same order
		ksort($pData);
		sort($columns);

		// Build query with
		$query = sprintf(
			"INSERT INTO {$pTableName} (%s) VALUES (%s);",
			implode(', ', $columns),
			implode(', ', array_keys($pData))
		);
		$insertStatement = $this->prepareQuery($query);

		// Execute query
		return $insertStatement->execute($pData);
	}


	/**
	 * Updates $pTable using $pCondition.
	 * Will be logged.
	 * @param string $pTableName The name of the table to update.
	 * @param string $pCondition The WHERE clause.
	 * @param array $pData An associative array representing the data to save. Keys represent the table columns.
	 * @return bool `true` if data was saved `false` otherwise.
	 */
	public function update ($pTableName, $pCondition, $pData = [])
	{
		// Connect through PDO
		$this->connect();

		// Parse $pCondition
		$pCondition = trim($pCondition);
		if (substr($pCondition, 0, 6) != 'WHERE ') {
			$pCondition = 'WHERE ' . $pCondition;
		}

		// Prepare data :
		//   * prefix the keys with ":" to use them in as PDO prepared statement placeholders
		//   * create an array of SET statements
		$sets = array();
		foreach ($pData as $column => $data) {
			$sets[] = "$column = :{$column}";
			$pData[":{$column}"] = $data;
			unset($pData[$column]);
		}

		// Build query
		$query = sprintf(
			"UPDATE {$pTableName} SET %s {$pCondition};",
			implode(', ', $sets)
		);
		$updateStatement = $this->prepareQuery($query);

		// Execute query
		return $updateStatement->execute($pData);
	}

	/**
	 * Finds a set of record in $pTableName searching for $pSearchTerm in the given $pColumnName.
	 * Will be logged.
	 * @param string $pTableName The name of the table to query.
	 * @param string $pColumnName The name of the column to search in.
	 * @param string $pSearchTerm The value to search for.
	 * @param int $pFetchStyle @see PDOStatement::fetchAll()
	 * @return mixed @see PDOStatement::fetchAll()
	 */
	public function findBy ($pTableName, $pColumnName, $pSearchTerm, $pFetchStyle = PDO::FETCH_ASSOC)
	{
		// Connect through PDO
		$this->connect();

		// Build query
		$statement = $this->prepareQuery(
			"SELECT * FROM {$pTableName} WHERE {$pColumnName} = :searchTerm;"
		);
		$statement->execute(
			[':searchTerm' => $pSearchTerm]
		);

		// Return fetched data
		return $statement->fetchAll($pFetchStyle);
	}
}