<?php

namespace Mynix\PgMigratorBundle\Command;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mynix\PgMigratorBundle\Services\MySQLScript;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * MySQL database script generator service
 *
 * @author Eugen Mihailescu
 *        
 */
class MySQLScriptCommand extends ContainerAwareCommand {
	/**
	 *
	 * @var array
	 */
	private $valid_args;
	
	/**
	 * Returns the global parameters given by keys
	 *
	 * @param array $keys
	 *        	Array of strings that represent the parameter keys
	 * @return array
	 */
	private function getGlobalParameters($keys) {
		$params = array ();
		
		foreach ( $keys as $key )
			$this->getContainer ()->hasParameter ( $key ) && $params [$key] = $this->getContainer ()->getParameter ( $key );
		
		return $params;
	}
	
	/**
	 * Callback function that handles the script errors
	 *
	 * @param string|\Exception $error        	
	 */
	public function onError($error) {
		$logger = $this->getContainer ()->get ( 'logger' );
		
		if ($logger) {
			$message = $error instanceof \Exception ? sprintf ( '%s (%d, line: %d)', $error->getMessage (), $error->getCode (), $error->getLine () ) : $error;
			
			$logger->error ( $message );
		}
	}
	
	/**
	 *
	 * @param string $name        	
	 */
	public function __construct($name = null) {
		$this->outputBufferLength = 1048576; // 1MB
		
		$this->pg_platform = new PostgreSqlPlatform ();
		
		$args = new MySQLScriptArguments ();
		
		$this->valid_args = $args->getArguments ();
		
		parent::__construct ( $name );
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure() {
		$this->setName ( 'db:mysql-script' );
		
		$this->setDescription ( 'Create PostgresMySql script of a MySql database' );
		
		foreach ( $this->valid_args as $arg_name => $arg_opt )
			$this->addArgument ( $arg_name, $arg_opt [0], $arg_opt [1] );
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Symfony\Component\Console\Command\Command::execute()
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$params = array ();
		
		$args = new MySQLScriptArguments ();
		
		foreach ( array_keys ( $this->valid_args ) as $arg_name ) {
			if ($input->hasArgument ( $arg_name ) && ! $args->isValidArgument ( $arg_name, $input->getArgument ( $arg_name ) ))
				$params [$arg_name] = null;
			else
				$params [$arg_name] = $input->getArgument ( $arg_name );
		}
		
		$request = new ParameterBag ( array (
				'mysql' => $params 
		) );
		
		$keys = array (
				'download_path',
				'data_path',
				'mysql_script_limit',
				'file_retention_time',
				'restricted_hosts' 
		);
		
		$script = new MySQLScript ( $request, $this->getGlobalParameters ( $keys ) );
		$script->setContainer ( $this->getContainer () );
		
		$script->onError = array (
				$this,
				'onError' 
		);
		
		$response = $script->run ();
		
		$output->writeln ( $response ['message'] ['content'] );
		
		if ($response ['success']) {
			$output->writeln ( sprintf ( 'Script saved at : %s', $this->getContainer ()->getParameter ( 'data_path' ) . '/' . $response ['filename'] ) );
		} else {
			$output->writeln ( sprintf ( '%s : %s', $response ['message'] ['file'], $response ['message'] ['line'] ) );
		}
		
		return $response ['success'];
	}
}