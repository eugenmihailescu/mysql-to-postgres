<?php

namespace Mynix\PgMigratorBundle\Services;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class GenericScript implements GenericScriptInterfce, ContainerAwareInterface {
	/**
	 * The current database connection
	 *
	 * @var Connection
	 */
	protected $conn;
	
	/**
	 *
	 * @var ContainerInterface
	 */
	protected $container;
	
	/**
	 * The JSON decoded REQUEST fields
	 *
	 * @var array
	 */
	protected $request;
	
	/**
	 * An array of application global parameters
	 *
	 * @var array
	 */
	protected $global_params;
	
	/**
	 * The temporary file where the statements are dumped
	 *
	 * @var string
	 */
	protected $temp_file;
	
	/**
	 * The progress total number of steps to be done
	 *
	 * @var integer
	 */
	protected $max_progress;
	
	/**
	 * The number of statements dumped to disk so far
	 *
	 * @var ingeger
	 */
	protected $current_progress;
	
	/**
	 * The time when the job started
	 *
	 * @var integer
	 */
	protected $job_start_time;
	
	/**
	 * The max.
	 * length of the output buffer that is collected
	 *
	 * @var int
	 */
	public $outputBufferLength;
	
	/**
	 * A callback where the errors are sent.
	 * Accepted argument is an string|Exception object
	 *
	 * @var callable
	 */
	public $onError;
	
	/**
	 * The constructor
	 *
	 * @param ParameterBag $request
	 *        	The fields sent via HTTP request
	 * @param array $parameters
	 *        	An array of key=value of parameters
	 */
	public function __construct(ParameterBag $request, $parameters = array()) {
		$this->global_params = $parameters;
		
		$this->request = $request;
		
		try {
			$this->outputBufferLength = 1048576; // 1MB
			
			$this->onError = null;
		} catch ( \Exception $e ) {
		}
	}
	/**
	 * Execute and returns the single-row-field query value
	 *
	 * @param string $query
	 *        	The query to execute
	 * @param Connection $conn
	 *        	The connection to use while running the query. When NULL then the default connection is used.
	 * @return boolean|mixed Returns the query value on success, false otherwise
	 */
	protected function getQueryValue($query, Connection $conn = null) {
		$conn = $conn ? $conn : $this->conn;
		
		try {
			$stmt = $conn->query ( $query );
			
			$result = $stmt->fetchColumn ();
			
			$stmt->closeCursor ();
		} catch ( \Exception $e ) {
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * Returns the value of an argument
	 *
	 * @param string $name        	
	 * @return bool Returns true if the argument given by name is either 1|on|true
	 */
	protected function getArgumentAsBool($name) {
		return in_array ( $this->request [$name], array (
				'1',
				'on',
				'true' 
		) );
	}
	
	/**
	 * Calculates and returns the ETA for a given progress
	 *
	 * @param int $start_time
	 *        	The action starting time
	 * @param int $max
	 *        	The progress maximum number of steps
	 * @param int $current
	 *        	The progress current step number
	 */
	protected function getETA($start_time, $max, $current) {
		$ellapsed = time () - $start_time;
		
		$eta = $ellapsed * ($max / $current - 1);
		
		return date ( 'H:i:s', $eta );
	}
	
	/**
	 * Creates a connection using the REQUEST parameters
	 *
	 * @param
	 *        	string Either 'mysql' or 'pgsql'
	 * @param array $params
	 *        	The connection parameter to use. When they are provided they will be emerged with the one provided within REQUEST.
	 *        	
	 * @return \Doctrine\DBAL\Connection
	 */
	protected function getConnection($driver = 'mysql', $params = array()) {
		$params = array_merge ( $this->request, $params );
		
		if (isset ( $this->global_params ['restricted_hosts'] ) && in_array ( $params ['host'], explode ( ',', $this->global_params ['restricted_hosts'] ) ))
			throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException ( sprintf ( $this->trans ( 'host.access_denied' ), $params ['host'] ) );
		
		if (empty ( $params ['charset'] ))
			unset ( $params ['charset'] );
		
		$params ['driver'] = 'pdo_' . $driver;
		
		$config = new Configuration ();
		
		file_put_contents ( '/tmp/params', print_r ( $params, 1 ) );
		return DriverManager::getConnection ( $params, $config );
	}
	
	/**
	 * Returns the progress data
	 *
	 * @return array Returns an array where the first element is the progress storage filename
	 *         and the second one is the progress data as array. The key of each element is the progress
	 *         specific token and the value represents the progress specs.
	 */
	protected function readProgress() {
		$filename = $this->global_params ['data_path'] . '/progress.json';
		
		$data = null;
		
		if (is_file ( $filename ))
			$data = json_decode ( file_get_contents ( $filename ), true );
		
		else
			$data = array ();
		
		return array (
				$filename,
				$data 
		);
	}
	
	/**
	 * Write a progress specific data to the application progress file
	 *
	 * @param string $token
	 *        	The progress specific token
	 * @param array $data
	 *        	The progress specific data
	 * @return Returns the number of bytes written or false on failure
	 */
	protected function writeProgress($token, $data = null) {
		list ( $tmp_file, $tmp_data ) = $this->readProgress ();
		
		if ($data)
			$tmp_data [$token] = $data;
		else {
			if (isset ( $tmp_data [$token] ))
				unset ( $tmp_data [$token] );
		}
		
		$result = file_put_contents ( $tmp_file, json_encode ( $tmp_data ) );
		
		if (session_status () == PHP_SESSION_NONE)
			session_start ();
		
		session_write_close ();
		
		return $result;
	}
	
	/**
	 * Write the given content to a filename using a specified charset
	 *
	 * @param string $filename
	 *        	The filename
	 * @param array $content
	 *        	The content to append into the given file
	 * @param string $charset
	 *        	The charset used for writting the content
	 * @return number|bool Return true if the content was written successfully,
	 *         -1 if the current content+filesize exceeds the maximum allowed size, false otherwise.
	 * @throws \Exception Throw an exception in case of file write error
	 */
	protected function writeToFile($filename, $content, $charset = null) {
	}
	
	/**
	 * Updates the progress given by `ptoken` using the current progress info
	 *
	 * @return \PgMigratorBundle\Services\Returns
	 */
	protected function updateProgress() {
		return $this->writeProgress ( $this->request ['ptoken'], array (
				'max' => $this->max_progress,
				'current' => $this->current_progress,
				'eta' => $this->getETA ( $this->job_start_time, $this->max_progress, $this->current_progress ),
				'percent' => $this->max_progress ? round ( 100 * $this->current_progress / $this->max_progress ) : 0 
		) );
	}
	
	/**
	 * Dump the SQL statements within $array to disk and update the progress
	 *
	 * @param array $array
	 *        	The items to dump to file
	 * @param string $charset
	 *        	The charset used for writting the content
	 * @return \PgMigratorBundle\Services\Returns
	 */
	protected function callback($array, $charset) {
		// write table insert records
		if (($write_status = $this->writeToFile ( $this->temp_file, $array, $charset )) < 0) {
			$this->current_progress = $this->max_progress;
			$this->updateProgress ();
			return $write_status;
		}
		
		$this->current_progress += count ( $array );
		
		// update the progress
		return $this->updateProgress ();
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \PgMigratorBundle\Services\GenericScriptInterfce::run()
	 */
	function run() {
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Symfony\Component\DependencyInjection\ContainerAwareInterface::setContainer()
	 */
	public function setContainer(ContainerInterface $container = null) {
		$this->container = $container;
	}
	
	/**
	 * Translate a given string
	 *
	 * @param string $string        	
	 * @return string
	 */
	public function trans($string) {
		$translator = $this->container->get ( 'translator' );
		
		if ($translator)
			return $translator->trans ( $string );
		
		return $string;
	}
}