<?php

namespace Mynix\PgMigratorBundle\Controller;

/**
 * PHP encoding helper class
 *
 * @author Eugen Mihailescu
 *        
 */
class NonQuotableColumnTypes {
	/**
	 * Maps the given charset to the equivalent PHP charset name
	 *
	 * @param string $charset
	 *        	The input (usually MySQL) charset name
	 * @return NULL|string Returns the equivalent PHP charset name if any, null otherwise
	 */
	private static function toPhpCharset($charset) {
		// no encoding support => no PHP charset match
		if (! extension_loaded ( 'mbstring' ))
			return null;
		
		$charset = strtolower ( $charset );
		
		$supported_encondings = mb_list_encodings ();
		
		$i_supported_encondings = array_map ( 'strtolower', $supported_encondings );
		
		// first try the easy way
		if (false !== ($key = array_search ( $charset, $i_supported_encondings ))) {
			return $supported_encondings [$key];
		}
		
		// pehaps the given charset contains some dashes
		foreach ( $i_supported_encondings as $key => $i_encoding ) {
			$a = str_replace ( '-', '', $charset );
			$b = str_replace ( '-', '', $i_encoding );
			
			// differend sizes regardless dashes => skip
			if (strlen ( $a ) != strlen ( $b ))
				continue;
			
			if ($a == $b)
				return $supported_encondings [$key];
		}
		
		// if we got so far then no match found
		return null;
	}
	
	/**
	 * Returns the fixed length SQL data types
	 *
	 * @return string[]
	 */
	public static function getFixedTypes() {
		return array (
				'bit',
				'boolean',
				'tinyint',
				'smallint',
				'int',
				'integer',
				'mediumint',
				'bigint',
				'float',
				'double',
				'decimal' 
		);
	}
	
	/**
	 * Returns the blob-like SQL data types
	 *
	 * @return string[]
	 */
	public static function getBlobTypes() {
		return array (
				'binary',
				'blob',
				'tinyblob',
				'mediumblob',
				'longblob' 
		);
	}
	
	/**
	 * Encodes a given input string using the specified encoding charset
	 *
	 * @param string $input
	 *        	The string to be encoded
	 * @param string $charset
	 *        	The target encoding charset (may not use exactly the PHP notation)
	 * @return string Returns the encoded string if the given encoding is PHP supported otherwise the input string
	 */
	public static function mb_encode($input, $charset = null) {
		$charset && $charset = self::toPhpCharset ( $charset );
		
		$charset && $input = mb_convert_encoding ( $input, $charset, 'ASCII' );
		
		return $input;
	}
}