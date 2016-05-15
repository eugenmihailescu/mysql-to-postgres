<?php

namespace Mynix\PgMigratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;

/**
 *
 * @author Eugen Mihailescu
 *        
 */
final class MySQLScriptArguments extends CommandScriptArguments {
	public function __construct($arguments = array()) {
		$arguments = array_merge ( array (
				'charset' => array (
						InputArgument::OPTIONAL,
						'The encoding charset to use',
						false 
				), // if supplied then argument does not allow NULL|empty value
				'droptables' => array (
						InputArgument::OPTIONAL,
						'Whether to add a DROP TABLE for each table (1|on|true)' 
				) 
		), $arguments );
		
		$this->driver = 'MySQL';
		
		parent::__construct ( $arguments );
	}
}