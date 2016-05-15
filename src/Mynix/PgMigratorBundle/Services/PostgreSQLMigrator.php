<?php

namespace Mynix\PgMigratorBundle\Services;

use Symfony\Component\HttpFoundation\ParameterBag;

class PostgreSQLMigrator extends GenericScript {
	
	/**
	 * The filename of the MySQL SQL script
	 *
	 * @var string
	 */
	private $script_filename;
	
	/**
	 * The constructor
	 *
	 * @param ParameterBag $request
	 *        	The fields sent via HTTP request
	 * @param array $parameters
	 *        	An array of key=value of parameters
	 */
	public function __construct(ParameterBag $request, $parameters = array()) {
		parent::__construct ( $request, $parameters );
		
		if (false !== strpos ( $request->get ( 'filename' ), PATH_SEPARATOR ))
			$this->script_filename = $request->get ( 'filename' );
		else
			$this->script_filename = $this->global_params ['data_path'] . '/' . $request->get ( 'filename' );
		
		$this->request = $request->get ( 'pgsql' );
	}
	
	/**
	 * Check whether the given PostgreSQL database exists on the server given by the current connection
	 *
	 * @param string $dbname        	
	 * @return boolean Returns true if the database exists, false otherwise
	 */
	private function dbExists($dbname) {
		// use the `postgres` default database as our connection database
		$params = array (
				'dbname' => 'postgres' 
		);
		
		return false !== $this->getQueryValue ( "SELECT 1 FROM pg_database WHERE datname = '$dbname'", $this->getConnection ( 'pgsql', $params ) );
	}
	
	/**
	 * Create a new PostgreSQL database on the current connection server
	 *
	 * @param string $dbname        	
	 * @return boolean Returns true on success, false otherwise
	 */
	private function createDb($dbname) {
		// use the `postgres` default database as our connection database
		$params = array (
				'dbname' => 'postgres' 
		);
		return false !== $this->getQueryValue ( "CREATE DATABASE $dbname", $this->getConnection ( 'pgsql', $params ) );
	}
	
	/**
	 * Check whether the PostgreSQL table exists or not
	 *
	 * @param string $table_name        	
	 * @return boolean Returns true if the table exists, false otherwise
	 */
	private function tableExists($table_name) {
		return false !== $this->getQueryValue ( "SELECT 1 FROM pg_catalog.pg_class WHERE relname = '$table_name' AND relkind = 'r'" );
	}
	
	/**
	 * Truncate the PostgreSQL table
	 *
	 * @param string $table_name        	
	 * @return boolean Returns true on success, false otherwise
	 */
	private function truncateTable($table_name) {
		return false !== $this->getQueryValue ( "TRUNCATE TABLE $table_name RESTART IDENTITY" );
	}
	
	/**
	 * Drop the PostgreSQL table from current database
	 *
	 * @param string $table_name        	
	 * @return boolean Returns true on success, false otherwise
	 */
	private function dropTable($table_name) {
		return false !== $this->getQueryValue ( "DROP TABLE IF EXISTS $table_name" );
	}
	
	/**
	 * Count the number of EOLs within a file
	 *
	 * @param string $filename
	 *        	The name of the file to scan
	 * @param bool $include_empty
	 *        	When true count also the empty lines, otherwise don't
	 * @return number Returns the number of EOLs
	 */
	private function countFileLines($filename, $include_empty = false) {
		$count = 0;
		
		if ($handle = fopen ( $filename, 'r' )) {
			while ( $line = fgets ( $handle ) ) {
				if (! $include_empty && empty ( $line ))
					continue;
				$count ++;
			}
			fclose ( $handle );
		}
		
		return $count;
	}
	
	/**
	 * Creates a SQL script file for all tables within a given MySQL database
	 *
	 * @return boolean[]|string[]|NULL[][]
	 */
	public function run() {
		$success = true;
		
		$output_buffer = '';
		
		// the default|empty response message
		$message = array (
				'content' => $this->trans ( 'pgsql.success' ),
				'file' => null,
				'line' => null 
		);
		
		// capture the output buffer
		ob_start ();
		
		try {
			// create the PostgreSQL database if not exists
			$this->dbExists ( $this->request ['dbname'] ) || $this->createDb ( $this->request ['dbname'] );
			
			$droptables = $this->getArgumentAsBool ( 'droptables' );
			
			$truncate = $this->getArgumentAsBool ( 'truncate' );
			
			$ignore_errors = $this->getArgumentAsBool ( 'ignore_errors' );
			
			$acid_batch = $this->getArgumentAsBool ( 'acid_batch' );
			
			$this->conn = $this->getConnection ( 'pgsql' );
			
			$acid_batch && $this->conn->beginTransaction ();
			
			$this->max_progress = $this->countFileLines ( $this->script_filename );
			
			$this->current_progress = 1;
			
			$this->job_start_time = time ();
			
			$handle = fopen ( $this->script_filename, 'r' );
			
			$create_tables = array ();
			
			$matches = null;
			
			$pattern_sufix = '.*?\s+(\w+)\s*\(';
			
			$create_table_pattern = '/CREATE TABLE (IF NOT EXISTS )?([^\s\(]+)/i';
			
			$insert_table_pattern = '/INSERT INTO' . $pattern_sufix . '/i';
			
			$eol_len = strlen ( PHP_EOL );
			
			if ($handle) {
				while ( $sql = fgets ( $handle ) ) {
					
					// remove any semi-column from the original line
					$sql = preg_replace ( '/(.*);$/', '$1', $sql );
					
					// empty or PHP_EOL ?
					if (empty ( $sql ) || strlen ( $sql ) <= $eol_len) {
						continue;
					}
					
					// if table exists at its creation time although `drop tables` option was set then make sure we drop the table first
					if ($droptables && preg_match ( $create_table_pattern, $sql, $matches ) && $this->tableExists ( $matches [2] )) {
						$this->dropTable ( $matches [2] );
					} else 
					// skip the drop table as per request
					if (! $droptables && preg_match ( '/DROP (TABLE|SEQUENCE)/i', $sql )) {
						$this->current_progress ++;
						$this->updateProgress ();
						continue;
					}
					if ($truncate) {
						// look for CREATE TABLE statement and store the table to be truncated later
						if (preg_match ( $create_table_pattern, $sql, $matches )) {
							$create_tables [$matches [2]] = false;
						} else {
							// look for INSERT INTO statement and if it's the first one for a given table then make sure we truncate the table first
							if (preg_match ( $insert_table_pattern, $sql, $matches ) && isset ( $create_tables [$matches [1]] ) && ! $create_tables [$matches [1]]) {
								// the table was truncated
								$create_tables [$matches [1]] = $this->truncateTable ( $matches [1] );
							}
						}
					}
					
					// execute the SQL line
					try {
						$this->conn->query ( $sql );
					} catch ( \Exception $e ) {
						if (! $ignore_errors) {
							$success = false;
							
							$message = array (
									'content' => $e->getMessage (),
									'file' => $e->getFile (),
									'line' => $e->getLine () 
							);
							
							break;
						}
					}
					
					// capture the output buffer
					strlen ( $output_buffer ) < $this->outputBufferLength && $output_buffer .= ob_get_clean ();
					
					$this->current_progress ++;
					
					$this->updateProgress ();
				}
				
				fclose ( $handle );
				
				if ($acid_batch) {
					if ($success)
						$this->conn->commit ();
					else
						$this->conn->rollBack ();
				}
			} else {
				$success = false;
				
				$error = error_get_last ();
				
				$message = array (
						'content' => $error ['message'],
						'file' => __FILE__,
						'line' => __LINE__ 
				);
			}
		} catch ( \Exception $e ) {
			$success = false;
			
			$message = array (
					'content' => $e->getMessage (),
					'file' => $e->getFile (),
					'line' => $e->getLine () 
			);
			
			is_callable ( $this->onError ) && call_user_func ( $this->onError, $e );
		}
		
		$response = array (
				'success' => $success,
				'message' => $message,
				'warning' => $output_buffer 
		);
		
		return $response;
	}
}