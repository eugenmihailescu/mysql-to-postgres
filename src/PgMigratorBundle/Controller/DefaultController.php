<?php

namespace PgMigratorBundle\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use PgMigratorBundle\Services\MySQLScript;
use PgMigratorBundle\Services\PostgreSQLMigrator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 *
 * @author Eugen Mihailescu
 *        
 */
class DefaultController extends Controller {
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
			$this->container->hasParameter ( $key ) && $params [$key] = $this->container->getParameter ( $key );
		
		return $params;
	}
	
	/**
	 * Cleans up the obsolete temporary files (possibly generated and not downloaded)
	 */
	private function cleanUpObsoleteFiles() {
		$file_retention_time = $this->container->getParameter ( 'file_retention_time' );
		
		$matches = array ();
		
		foreach ( glob ( $this->container->getParameter ( 'data_path' ) . '/sql_.*' ) as $filename ) {
			if (preg_match ( '/\.([^.]+$)/', $filename, $matches ))
				if (time () - intval ( $matches [1] ) > $file_retention_time)
					unlink ( $filename );
		}
	}
	
	/**
	 * Translates a string
	 *
	 * @param string $string
	 *        	Return the translated string
	 */
	public function trans($string) {
		return $this->get ( 'translator' )->trans ( $string );
	}
	
	/**
	 * Renders the home page
	 *
	 * @param Request $request        	
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function indexAction(Request $request) {
		// replace this example code with whatever you need
		return $this->render ( 'PgMigratorBundle::homepage.html.twig' );
	}
	
	/**
	 * Starts the MySQL script job asynchronously
	 *
	 * @param Request $request        	
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function generateSqlScriptAction(Request $request) {
		// make sure we clean-up the obsolete files first (disk space is costy)
		$this->cleanUpObsoleteFiles ();
		
		try {
			
			$keys = array (
					'download_path',
					'data_path',
					'mysql_script_limit' 
			);
			
			$script = new MySQLScript ( $request->request, $this->getGlobalParameters ( $keys ) );
			$script->setContainer ( $this->container );
			
			$response = $script->run ();
		} catch ( \Exception $e ) {
			$response = array (
					'success' => false,
					'message' => $e->getMessage () 
			);
		}
		
		return Response::create ( json_encode ( $response ) );
	}
	
	/**
	 * Run a sql script file against a PostgreSQL database
	 *
	 * @param Request $request        	
	 */
	public function runSqlScriptToPostgresAction(Request $request) {
		try {
			$keys = array (
					'download_path',
					'data_path' 
			);
			
			$migrator = new PostgreSQLMigrator ( $request->request, $this->getGlobalParameters ( $keys ) );
			$migrator->setContainer ( $this->container );
			
			$response = $migrator->run ();
		} catch ( \Exception $e ) {
			$response = array (
					'success' => false,
					'message' => $e->getMessage () 
			);
		}
		
		return Response::create ( json_encode ( $response ) );
	}
	
	/**
	 * Sends back the file given by name
	 *
	 * @param string $tmpname
	 *        	The basename of the temporary file to be sent
	 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
	 */
	public function downloadFileAction($tmpname) {
		$tmp_dir = $this->container->getParameter ( 'download_path' );
		
		$headers = array (
				'Content-Disposition' => 'attachment; filename=' . $tmpname . '.sql',
				'Content-Type' => 'application/octet-stream' 
		);
		
		$filename = $tmp_dir . '/' . $tmpname;
		
		if (is_file ( $filename )) {
			$response = new BinaryFileResponse ( $filename, 200, $headers );
			
			$response->deleteFileAfterSend ( true );
		} else
			throw new NotFoundHttpException ( 'The requested file does not exists anymore on server' );
		
		return $response;
	}
}

