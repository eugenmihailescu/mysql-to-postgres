<?php

namespace Mynix\PgMigratorBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\PostgreSQLKeywords;
use Doctrine\DBAL\Schema\AbstractAsset;

/**
 * PostgreSQL platform helper class
 *
 * @author Eugen Mihailescu
 *        
 */
final class PostgreSQLAsset extends AbstractAsset {
	/**
	 *
	 * @var string
	 */
	private $platform;
	
	/**
	 *
	 * @var array
	 */
	private $keywords;
	
	/**
	 *
	 * @var \PDO
	 */
	private $conn;
	
	/**
	 * The class constructor
	 *
	 * @param Connection $conn        	
	 * @param AbstractPlatform $platform        	
	 */
	final public function __construct(Connection $conn, AbstractPlatform $platform) {
		$this->platform = $platform;
		
		$this->keywords = new PostgreSQLKeywords ();
		
		$dsn = sprintf ( 'mysql:host=%s;dbname=%s', $conn->getHost (), $conn->getDatabase () );
		
		$this->conn = new \PDO ( $dsn, $conn->getUsername (), $conn->getPassword () );
	}
	
	/**
	 * Encloses within quotes the given value
	 *
	 * @param mixed $value
	 *        	The input value to be quoted
	 * @param string $type
	 *        	Hint for the data type of the value's column
	 * @param string $charset
	 *        	Hint for the charset to use for encoding the input value
	 * @return mixed The input value converted and enclosed within quotes, if applicable
	 */
	public function quoteColValue($value, $type = null, $charset = null) {
		if (null === $value)
			return 'null';
		elseif ((null !== $type && in_array ( $type, NonQuotableColumnTypes::getFixedTypes () )) || is_int ( $value ) || is_float ( $value ) || is_bool ( $value )) {
			return $value;
		}
		
		// escape single quotes
		$value = str_replace ( "'", "''", $value );
		
		// escape #0, CR, LF, \, ^Z
		$value = addcslashes ( $value, "\000\n\r\032" );
		
		// escape special chars within blob by E-prefix
		$is_blob = null !== $type && in_array ( $type, NonQuotableColumnTypes::getBlobTypes () );
		
		if ($is_blob) {
			if ($charset)
				$value = "convert_to('$value','$charset')";
			else
				$value = "E'$value'";
		} else
			$value = "'$value'";
		
		return $value;
	}
	
	/**
	 * Encloses within quote the given column name
	 *
	 * @param string $colname
	 *        	The column name to be encloses
	 * @return string Returns the enclosed column name if applicable, otherwise the unchanged column name
	 */
	public function quoteColName($colname) {
		if ($this->keywords->isKeyword ( $colname )) {
			return '"' . $colname . '"';
		}
		return $colname;
	}
}