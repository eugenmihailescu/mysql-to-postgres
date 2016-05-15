<?php

namespace Mynix\PgMigratorBundle\Services;

use Mynix\PgMigratorBundle\Controller\NonQuotableColumnTypes;
use Mynix\PgMigratorBundle\Controller\PostgreSQLAsset;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\PostgreSQLKeywords;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\HttpFoundation\ParameterBag;
use Doctrine\ORM\Query;

/**
 * MySQL database script generator service
 *
 * @author Eugen Mihailescu
 *        
 */
class MySQLScript extends GenericScript {
	
	/**
	 *
	 * @var PostgreSqlPlatform
	 */
	private $pg_platform;
	
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
		
		$this->pg_platform = new PostgreSqlPlatform ();
		
		$this->request = $request->get ( 'mysql' );
	}
	
	/**
	 * Maps a MySQL data type for a given column to its PostgreSQL equivalent
	 *
	 * @param Table $table
	 *        	The table that contains the column
	 * @param Column $column
	 *        	The column
	 * @return array Returns an array where the first element is the PostgreSQL equivalent type,
	 *         the second is the data lenght (null if not applicable) and the third one the SQL-like data type string
	 */
	private function mySqlType2PostgreSqlType(Table $table, Column $column) {
		$type = strtolower ( $this->raw_cols_defs [$table->getName ()] [$column->getName ()] ['type'] );
		
		$length = $column->getLength ();
		
		$supports_length = false;
		
		// by default we assume that PostgreSQL datatype == MySQL datatype
		$pgSqlType = $type;
		
		// https://en.wikibooks.org/wiki/Converting_MySQL_to_PostgreSQL
		switch ($type) {
			case 'bigint' :
			case 'boolean' :
			case 'date' :
			case 'time' :
			case 'decimal' :
				$pgSqlType = $type;
				break;
			case 'char' :
			case 'varchar' :
				$pgSqlType = $type;
				$supports_length = true;
				break;
			case 'int' :
			case 'integer' :
			case 'mediumint' :
				$pgSqlType = 'integer';
				break;
			case 'datetime' :
				$pgSqlType = 'timestamp';
				break;
			case 'datetimetz' :
				$pgSqlType = 'timestamptz';
				break;
			case 'string' :
				$pgSqlType = 'varchar';
				$supports_length = true;
				break;
			case 'tinyint' :
			case 'smallint' :
				$pgSqlType = 'smallint';
				break;
			case 'text' :
			case 'tinytext' :
			case 'mediumtext' :
			case 'longtext' :
				$pgSqlType = 'text';
				break;
			case 'binary' :
			case 'blob' :
			case 'tinyblob' :
			case 'mediumblob' :
			case 'longblob' :
				$pgSqlType = 'bytea';
				break;
			case 'float' :
			case 'double' :
				$pgSqlType = 'float8';
				break;
			case 'enum' :
				$pgSqlType = 'varchar';
				$length = 255;
				$supports_length = true;
				break;
			case 'guid' :
				$pgSqlType = 'char';
				$supports_length = true;
				break;
		}
		
		return array (
				$pgSqlType,
				$supports_length ? $length : null,
				$pgSqlType . ($supports_length ? sprintf ( '(%d)', $length ) : '') 
		);
	}
	
	/**
	 * Creates a unique PostgreSQL Sequence name for the given column
	 *
	 * @param Table $table
	 *        	The table that contains the column
	 * @param Column $column
	 *        	The column for which creates the sequence name
	 * @return string
	 */
	private function getSeqName(Table $table, Column $column) {
		return sprintf ( '%s_%s_seq', $table->getName (), $column->getName () );
	}
	
	/**
	 * Returns the SQL constraints for a given column
	 *
	 * @param Table $table
	 *        	The table that column belongs to
	 * @param Column $column
	 *        	The column
	 * @return array Returns an array of constraints applicable to the given table column
	 */
	private function getColumnConstraints(Table $table, Column $column) {
		$constraints = array ();
		
		$column->getNotnull () && $constraints [] = 'NOT NULL';
		
		$default_value = $column->getDefault ();
		
		$col_type = $this->mySqlType2PostgreSqlType ( $table, $column );
		
		if ('bytea' == $col_type [0]) {
			$default_value = preg_replace ( '/[\x00-\x1F\x80-\xFF]/', '', $default_value );
		}
		
		if ($column->getAutoincrement ()) {
			$constraints [] = sprintf ( "DEFAULT nextval('%s'::regclass)", $this->getSeqName ( $table, $column ) );
		} elseif (null !== $default_value) {
			if (false !== strpos ( $col_type [0], 'timestamp' ) || false !== strpos ( $col_type [0], 'date' ))
				$constraints [] = sprintf ( "DEFAULT NULLIF('%s','1970-01-01')::%s", $default_value, $col_type [0] );
			elseif (in_array ( $col_type [0], NonQuotableColumnTypes::getFixedTypes () ))
				$constraints [] = sprintf ( "DEFAULT '%s'::%s", preg_replace ( '/.*(\d+).*/', '$1', $default_value ), $col_type [0] );
			else
				$constraints [] = sprintf ( "DEFAULT '%s'::%s", $default_value, $col_type [0] );
		}
		
		return $constraints;
	}
	
	/**
	 * Returns the total number of records within current database
	 *
	 * @return int
	 */
	private function getCurrentDbRowCount() {
		$db_rowcount = 0;
		
		foreach ( $this->conn->getSchemaManager ()->listTables () as $table ) {
			$db_rowcount += $this->getQueryValue ( sprintf ( 'SELECT COUNT(*) FROM %s', $table->getName () ) );
		}
		
		return $db_rowcount;
	}
	
	/**
	 * Returns the SQL create index statements for all indexes of a given table
	 *
	 * @param Table $table
	 *        	The table for which returns the SQL create statements
	 * @return array Returns an array with one entry for each CREATE INDEX SQL statement
	 */
	private function getTableIndexes(Table $table) {
		$indexes = array ();
		
		$pg_platform = $this->pg_platform;
		
		$doctrine_indexes = $table->getIndexes ();
		
		$getQuotedColumns = function ($key_name) use (&$doctrine_indexes, &$pg_platform) {
			
			foreach ( $doctrine_indexes as $index )
				if ($index->getName () == $key_name)
					return $index->getQuotedColumns ( $pg_platform );
			
			return '?';
		};
		
		// use the native metadata ; Doctrine doesn't support FullText indexes
		$stmt = $this->conn->query ( sprintf ( 'SHOW INDEXES FROM %s', $table->getName () ) );
		$raw_indexes = $stmt->fetchAll ( \PDO::FETCH_ASSOC );
		$stmt->closeCursor ();
		
		foreach ( $raw_indexes as $index ) {
			if ($index ['Key_name'] == 'PRIMARY' || isset ( $indexes [$index ['Key_name']] ))
				continue;
			
			$full_text = strtolower ( $index ['Index_type'] ) != 'btree';
			
			$indexes [$index ['Key_name']] = sprintf ( 'CREATE %sINDEX IF NOT EXISTS ix_%s_%s ON %s USING %s %s)%s', $index ['Non_unique'] ? '' : 'UNIQUE ', $index ['Key_name'], $table->getName (), $table->getName (), $full_text ? "gin(to_tsvector('english'," : 'btree(', implode ( ',', $getQuotedColumns ( $index ['Key_name'] ) ), $full_text ? ')' : '' );
		}
		
		return $indexes;
	}
	
	/**
	 * Returns the SQL create foreign key statements for all foreign keys of a given table
	 *
	 * @param Table $table
	 *        	The table for which returns the SQL create statements
	 * @return array Returns an array of SQL statements for creating these foreign keys
	 */
	private function getTableForeignKeys(Table $table) {
		return array ();
		
		$fkeys = array ();
		
		foreach ( $table->getForeignKeys () as $fkey ) {
			
			$on_del = $fkey->onDelete ();
			empty ( $on_del ) && $on_del = 'NO ACTION';
			
			$on_upd = $fkey->onUpdate ();
			empty ( $on_upd ) && $on_upd = 'NO ACTION';
			
			$fkeys [] = sprintf ( 'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE %s ON DELETE %s', $table->getName (), $fkey->getName (), $fkey->getColumns (), $fkey->getForeignTableName (), implode ( ',', $fkey->getQuotedForeignTableName ( $this->pg_platform ) ) );
			$fkeys [] = sprintf ( 'CREATE INDEX fki_%s ON %s (%s)', $fkey->getName (), $table->getName (), $fkey->getColumns () );
		}
		
		return $fkeys;
	}
	
	/**
	 * Returns the SQL create sequence statement for a given column
	 *
	 * @param Table $table
	 *        	The table the column belongs to
	 * @param Column $column
	 *        	The column for which the SQL create statement is returned
	 * @param bool $forcebly
	 *        	When true then drop and recreate the sequence otherwise create the sequence only if it doesn't exist
	 * @return array Returns an array with DROP SEQUENCE|CREATE SEQUENCE SQL statements
	 */
	private function getColSeqCreate(Table $table, Column $column, $forcebly = true) {
		$seq_name = $this->getSeqName ( $table, $column );
		
		$seqs = array ();
		
		$forcebly && $seqs [] = sprintf ( 'DROP SEQUENCE IF EXISTS %s', $seq_name );
		
		$prefix = $forcebly ? '' : sprintf ( "DO $$ BEGIN IF NOT EXISTS(SELECT * FROM pg_class WHERE relname = '%s') THEN ", $seq_name );
		$sufix = $forcebly ? '' : '; END IF; END; $$';
		
		$seqs [] = sprintf ( '%sCREATE SEQUENCE %s INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1%s', $prefix, $seq_name, $sufix );
		
		return $seqs;
	}
	
	/**
	 * Registers the MySQL-PostgreSQL type mappings
	 *
	 * @param AbstractPlatform $platform
	 *        	The target platform (eg. PostgreSQL)
	 */
	private function registerTypeMappings(AbstractPlatform $platform) {
		$platform->registerDoctrineTypeMapping ( 'bit', 'boolean' );
	}
	
	/**
	 * Returns the column list for a SQL create table statement
	 *
	 * @param Table $table
	 *        	The tables for which to return the column list
	 * @return array Returns an array that contains an entry for each SQL column type
	 */
	private function getTableColsCreate(Table $table) {
		$autoinc_cols = array ();
		
		$columns_sql = array ();
		
		$platform_keywords = new PostgreSQLKeywords ();
		
		foreach ( $table->getColumns () as $column ) {
			
			$column->getAutoincrement () && $autoinc_cols [] = $column;
			
			$col_name = $column->getName ();
			
			$platform_keywords->isKeyword ( $col_name ) && $col_name = $column->getQuotedName ( $this->pg_platform );
			
			$col_type = $this->mySqlType2PostgreSqlType ( $table, $column );
			
			$col_constr = $this->getColumnConstraints ( $table, $column );
			
			$columns_sql [] = sprintf ( '%s %s %s', $col_name, $col_type [2], implode ( ' ', $col_constr ) );
		}
		
		return array (
				$columns_sql,
				$autoinc_cols 
		);
	}
	
	/**
	 * Returns the INSERT statements for all rows of a given table
	 *
	 * @param Table $table
	 *        	The table for which INSERT statements are created
	 * @param string $charset
	 *        	A hint for the charset to use while converting the source column values
	 * @param callable $callback
	 *        	A callback to dump the SQL statements to disk. When NULL then ignored.
	 * @param int $buffer_size
	 *        	The number of bytes to be filled before executing disk dump the callback
	 * @return array When callback is provided returns true otherwise an array containing INSERT INTO SQL statements
	 */
	private function createTableInsertStmts(Table $table, $charset = null, $callback = null, $buffer_size = 1048576) {
		$stmt = $this->conn->query ( sprintf ( 'SELECT * FROM %s', $table->getName () ) );
		
		$buffer_length = 0;
		
		$rows_sql = array ();
		
		$asset = new PostgreSQLAsset ( $this->conn, $this->pg_platform );
		
		while ( $row = $stmt->fetch ( \PDO::FETCH_ASSOC ) ) {
			$values = array ();
			
			$cols = array ();
			
			foreach ( $row as $col_name => $raw_value ) {
				$col_def = $table->getColumn ( $col_name );
				
				$cols [] = $asset->quoteColName ( $col_name );
				
				$col_type = strtolower ( $col_def->getType () );
				
				// fix some naughty values (like zeroed datetime or invalid booleans)
				if (false !== strpos ( $col_type, 'timestamp' )) {
					strtotime ( $raw_value ) < 0 && $raw_value = date ( 'Y-m-d H:i:s' );
				} elseif (false !== strpos ( $col_type, 'date' )) {
					strtotime ( $raw_value ) < 0 && $raw_value = date ( 'Y-m-d' );
				} elseif (strpos ( $col_type, 'boolean' ) || strpos ( $col_type, 'bit' )) {
					is_bool ( $raw_value ) || $raw_value = 0;
				}
				
				$values [] = $asset->quoteColValue ( $raw_value, $col_type, $charset );
			}
			
			$sql = sprintf ( 'INSERT INTO %s (%s) VALUES(%s)', $table->getName (), implode ( ',', $cols ), implode ( ',', $values ) );
			
			if ($callback) {
				$stmt_len = strlen ( $sql );
				
				$buffer_length += $stmt_len;
				
				if ($buffer_length >= $buffer_size) {
					if (call_user_func ( $callback, $rows_sql, $charset )) {
						$buffer_length = $stmt_len;
						$rows_sql = array ();
					}
				}
			}
			
			$rows_sql [] = sprintf ( 'INSERT INTO %s (%s) VALUES(%s)', $table->getName (), implode ( ',', $cols ), implode ( ',', $values ) );
		}
		
		$stmt->closeCursor ();
		
		if ($callback) {
			return call_user_func ( $callback, $rows_sql, $charset );
		}
		
		return $rows_sql;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \PgMigratorBundle\Services\GenericScript::writeToFile()
	 */
	protected function writeToFile($filename, $content, $charset = null) {
		if (empty ( $content )) {
			return false;
		}
		
		$content = implode ( ';' . PHP_EOL, $content ) . ';' . PHP_EOL . PHP_EOL;
		
		// a maximum file size constraint is imposed (see `mysql_script_limit` in config.yml)
		$mysql_script_limit = $this->global_params ['mysql_script_limit'];
		
		if ($mysql_script_limit && is_file ( $filename ) && filesize ( $filename ) + strlen ( $content ) >= $mysql_script_limit) {
			return - 1;
		}
		// convert the output buffer to specified charset
		$charset && $content = NonQuotableColumnTypes::mb_encode ( $content, $charset );
		
		if (! file_put_contents ( $filename, $content, FILE_APPEND )) {
			
			$error = error_get_last ();
			
			throw new \Exception ( $error ['message'], $error ['type'] );
		}
		
		return true;
	}
	
	/**
	 *
	 * @param Connection $conn        	
	 * @param Table $table        	
	 */
	private function initTableRawDefs(Table $table) {
		$stmt = $this->conn->query ( sprintf ( "SELECT column_name,data_type,character_maximum_length FROM information_schema.COLUMNS where table_name='%s'", $table->getName () ) );
		
		$this->raw_cols_defs [$table->getName ()] = array ();
		
		foreach ( $stmt->fetchAll ( \PDO::FETCH_ASSOC ) as $row_def )
			$this->raw_cols_defs [$table->getName ()] [$row_def ['column_name']] = array (
					'type' => $row_def ['data_type'],
					'length' => $row_def ['character_maximum_length'] 
			);
		
		$stmt->closeCursor ();
	}
	
	/**
	 * Creates a temporary filename to the global download path
	 *
	 * @return string Returns the name to the given filename
	 */
	private function getTempFile() {
		$tmpdir = $this->global_params ['download_path'];
		
		empty ( $tmpdir ) && $tmpdir = dirname ( dirname ( dirname ( __DIR__ ) ) ) . '/var/tmp';
		
		is_dir ( $tmpdir ) || mkdir ( $tmpdir );
		
		$tmpfile = $tmpdir . '/' . uniqid ( 'sql_' ) . '.' . time ();
		
		// this should never be necessary
		is_file ( $tmpfile ) && unlink ( $tmpfile );
		
		return $tmpfile;
	}
	
	/**
	 * Creates a SQL script file for all tables within a given MySQL database
	 *
	 * @return boolean[]|string[]|NULL[][]
	 */
	public function run() {
		$success = false;
		
		$output_buffer = '';
		
		// the default|empty response message
		$message = array (
				'content' => $this->trans ( 'mysql.success' ),
				'file' => null,
				'line' => null 
		);
		
		// capture the output buffer
		ob_start ();
		
		try {
			
			$droptables = in_array ( $this->request ['droptables'], array (
					'1',
					'on',
					'true' 
			) );
			
			$charset = $this->request ['charset'];
			
			empty ( $charset ) && $charset = null;
			
			$this->conn = $this->getConnection ();
			
			$platform = $this->conn->getDatabasePlatform ();
			
			$this->registerTypeMappings ( $platform );
			
			$sm = $this->conn->getSchemaManager ();
			
			$tables = $sm->listTables ();
			
			/**
			 * this is an early estimation of progress max which
			 * is given by the total # of rec in database +
			 * the total # of create table/index SQL statements
			 */
			$this->max_progress = $this->getCurrentDbRowCount ();
			
			$this->current_progress = 1;
			
			$this->job_start_time = time ();
			
			$this->temp_file = $this->getTempFile ();
			
			// iterate all database tables
			foreach ( $tables as $table_obj ) {
				
				// store the table's raw columns defs into cache
				$this->initTableRawDefs ( $table_obj );
				
				$create_sql = array ();
				
				$droptables && $create_sql [] = sprintf ( 'DROP TABLE IF EXISTS %s', $table_obj->getName () );
				
				$columns_sql = array ();
				
				$colsDef = $this->getTableColsCreate ( $table_obj );
				$columns_sql = $colsDef [0];
				$autoinc_cols = $colsDef [1];
				
				// autoincrement sequence must exist before table creation
				foreach ( $autoinc_cols as $column ) {
					$create_sql = array_merge ( $create_sql, $this->getColSeqCreate ( $table_obj, $column, $droptables ) );
				}
				
				$table_sql = sprintf ( 'CREATE TABLE %s%s', $droptables ? '' : 'IF NOT EXISTS ', $table_obj->getName () );
				
				$table_sql .= '(' . implode ( ',', $columns_sql );
				
				if ($table_obj->hasPrimaryKey ()) {
					$pk = $table_obj->getPrimaryKey ();
					$table_sql .= sprintf ( ',CONSTRAINT %s_pkey PRIMARY KEY (%s)', $table_obj->getName (), implode ( ',', $pk->getQuotedColumns ( $this->pg_platform ) ) );
				}
				
				$table_sql .= ')';
				
				$create_sql [] = $table_sql;
				
				$create_sql = array_merge ( $create_sql, $this->getTableForeignKeys ( $table_obj ), $this->getTableIndexes ( $table_obj ) );
				
				// increase the progress maximum size
				$this->max_progress += count ( $create_sql );
				
				$mysql_script_limit = false;
				
				// write table create statement and update the progress via callback
				if (call_user_func ( array (
						$this,
						'callback' 
				), $create_sql, $charset ) < 0) {
					$mysql_script_limit = true;
				}
				
				// create the insert SQL statements, dump them to disk and update the progress via callback
				if ($mysql_script_limit || $this->createTableInsertStmts ( $table_obj, $charset, array (
						$this,
						'callback' 
				) ) < 0) {
					$mysql_script_limit = true;
				}
				
				// capture the output buffer
				strlen ( $output_buffer ) < $this->outputBufferLength && $output_buffer .= ob_get_clean ();
				
				if ($mysql_script_limit) {
					// $this->current_progress = $this->max_progress;
					// $this->updateProgress();
					// the filesize exceeded the maximum allog file size (see `mysql_script_limit` in config.yml)
					break;
				}
			}
			
			// clean-up the progress
			// progress is cleaned-up at UI request via /clearprogress
			
			$success = true;
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
		
		$success && $response ['filename'] = basename ( $this->temp_file );
		
		return $response;
	}
}