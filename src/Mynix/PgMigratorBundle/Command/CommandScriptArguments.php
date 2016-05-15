<?php

namespace Mynix\PgMigratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;

/**
 *
 * @author Eugen Mihailescu
 *        
 */
class CommandScriptArguments {
	/**
	 * Array of valid arguments supported by this command
	 *
	 * @var array
	 */
	private $arguments;
	
	/**
	 * The name of the database driver (mysql|pgsql)
	 *
	 * @var string
	 */
	protected $driver = '';
	
	/**
	 * The constructor
	 */
	public function __construct($arguments = array()) {
		$this->arguments = array (
				'host' => array (
						InputArgument::REQUIRED,
						sprintf ( 'The %s host or IP address', $this->driver ) 
				),
				'dbname' => array (
						InputArgument::REQUIRED,
						sprintf ( 'The %s database name', $this->driver ) 
				),
				'user' => array (
						InputArgument::REQUIRED,
						sprintf ( 'The %s user name', $this->driver ) 
				) 
		);
		
		// insert the required input arguments just before the optional one
		foreach ( $arguments as $key => $value )
			if ($value [0] == InputArgument::REQUIRED)
				$this->arguments [$key] = $value;
		
		$this->arguments = array_merge ( $this->arguments, array (
				'password' => array (
						InputArgument::OPTIONAL,
						sprintf ( 'The %s password', $this->driver ) 
				),
				'ptoken' => array (
						InputArgument::OPTIONAL,
						'An unique token string used to identify the command progress' 
				),
				'port' => array (
						InputArgument::OPTIONAL,
						sprintf ( 'The %s server port (default 3306)', $this->driver ) 
				) 
		) );
		
		// insert the non-required input arguments just after the optional one
		foreach ( $arguments as $key => $value )
			if ($value [0] != InputArgument::REQUIRED)
				$this->arguments [$key] = $value;
	}
	
	/**
	 * Check whether the given argument is valid or not
	 *
	 * @param string $name        	
	 * @param mixed $value        	
	 * @return boolean Returns true if a valid argument supplied, false otherwise
	 */
	public function isValidArgument($name, $value) {
		// argument is defined
		$valid = isset ( $this->arguments [$name] );
		
		// either is not empty or it allows empty value
		$valid &= ! empty ( $value ) || ! isset ( $this->arguments [$name] [2] ) || $this->arguments [$name] [2];
		
		return $valid;
	}
	
	/**
	 * Returns a list of arguments
	 *
	 * @return array
	 */
	public function getArguments() {
		// arg_name => arg_options
		// arg_options={0: InputArgument, 1: description, 2: true when allows NULL, false otherwise (optional) }
		return $this->arguments;
	}
}