<?php

namespace PgMigratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;

/**
 *
 * @author Eugen Mihailescu
 *        
 */
final class PostgreSQLScriptArguments extends CommandScriptArguments {
	public function __construct($arguments = array()) {
		$this->driver = 'Postgress';
		
		$arguments = array_merge ( array (
				'filename' => array (
						InputArgument::REQUIRED,
						sprintf ( 'The .sql script filename to run against the %s server', $this->driver ),
						false 
				),
				'droptables' => array (
						InputArgument::OPTIONAL,
						'Drop the existent tables before migration (1|on|true)',
						false 
				), // if supplied then argument does not allow NULL|empty value
				'truncate' => array (
						InputArgument::OPTIONAL,
						'Truncate the table existent data before insert (1|on|true)' 
				),
				'ignore_errors' => array (
						InputArgument::OPTIONAL,
						'Ignore the eventual errors by trying to continue (1|on|true)' 
				),
				'acid_batch' => array (
						InputArgument::OPTIONAL,
						'Encloses the whole migration script within a transaction (1|on|true)' 
				) 
		), $arguments );
		
		parent::__construct ( $arguments );
	}
}