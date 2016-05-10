<?php

namespace PgMigratorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class MySQLProgressController extends Controller {
	/**
	 * Returns the progress data
	 *
	 * @return array Returns an array where the first element is the progress storage filename
	 *         and the second one is the progress data as array. The key of each element is the progress
	 *         specific token and the value represents the progress specs.
	 */
	private function readProgress() {
		$filename = $this->container->getParameter ( 'data_path' ) . '/progress.json';
		
		if (session_status () == PHP_SESSION_NONE)
			session_start ();
		
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
	 * Read te progress for the given token
	 *
	 * @param string $token        	
	 * @return array|NULL Return the progress info if found, NULL otherwise
	 */
	private function getProgress($token) {
		list ( $tmp_file, $tmp_data ) = $this->readProgress ();
		
		if (isset ( $tmp_data [$token] ))
			return $tmp_data [$token];
		
		return null;
	}
	
	/**
	 * Sends back the progress data for a given token
	 *
	 * @param string $token
	 *        	The unique token that identifies the requested progress
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getProgressAction($token) {
		$data = $this->getProgress ( $token );
		
		if (null == $data) {
			$trans = $this->get ( 'translator' );
			
			$message = array (
					'content' => $trans->trans ( 'no.progress' ),
					'file' => __FILE__,
					'line' => __LINE__ 
			);
			
			$data = array (
					'success' => false,
					'message' => $message,
					'warning' => null,
					'progress' => null 
			);
		} else {
			$data = array (
					'success' => true,
					'progress' => $data 
			);
		}
		
		$response = new Response ( json_encode ( $data ) );
		
		return $response;
	}
	
	/**
	 * Removes a progress element from the progress monitor file
	 *
	 * @param string $token        	
	 */
	public function clearProgressAction($token) {
		try {
			list ( $tmp_file, $tmp_data ) = $this->readProgress ();
			
			if (isset ( $tmp_data [$token] ))
				unset ( $tmp_data [$token] );
			
			file_put_contents ( $tmp_file, json_encode ( $tmp_data ) );
			
			if (session_status () == PHP_SESSION_NONE)
				session_start ();
			
			session_write_close ();
			
			$data = array (
					'success' => true 
			);
		} catch ( \Exception $e ) {
			$message = array (
					'content' => $e->getMessage (),
					'file' => $e->getFile (),
					'line' => $e->getLine () 
			);
			$data = array (
					'success' => false,
					'message' => $message,
					'warning' => null 
			);
		}
		return Response::create ( json_encode ( array (
				$data 
		) ) );
	}
}