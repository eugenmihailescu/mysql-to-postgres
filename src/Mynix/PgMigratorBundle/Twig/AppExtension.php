<?php

namespace Mynix\PgMigratorBundle\Twig;

class AppExtension extends \Twig_Extension {
	public function getFilters() {
		return array (
				new \Twig_SimpleFilter ( 'bytestr', array (
						$this,
						'toByteStr' 
				) ) 
		);
	}
	/**
	 * Returns the size in units in human-readable form (eg.
	 * 124 KB or 72 MB)
	 *
	 * @param int $number        	
	 * @param int $precision        	
	 * @param int $return_what
	 *        	When 0 returns the size formated as string; when 1 returns the exponent of 1024; when 2
	 *        	returns the unit name
	 * @return string
	 */
	public function toByteStr($number, $precision = 2, $return_what = 0) {
		if (PHP_INT_MAX == $number)
			return _esc ( 'unknown' );
			
			// read more here: http://en.wikipedia.org/wiki/Kilobyte
		$units = array (
				'B',
				'KiB',
				'MiB',
				'GiB',
				'TiB',
				'PiB' 
		);
		for($i = 0; abs ( $number ) >= 1024; $i ++)
			$number /= 1024;
		
		$i = $i + 1 > count ( $units ) ? count ( $units ) - 1 : $i;
		
		if ($return_what == 1)
			return $i;
		elseif ($return_what == 2)
			return $units [$i];
		else
			return sprintf ( '%.' . $precision . 'f %s', $number, $units [$i] );
	}
	public function getName() {
		return 'app_extension';
	}
}