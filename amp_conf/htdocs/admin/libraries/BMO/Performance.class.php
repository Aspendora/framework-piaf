<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * Performance logging
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
class Performance {

	private $doperf = false;
	private $lasttick = false;
	private $lastmem = false;

	private $current = array();

	/**
	 * Turn Performance Logging on
	 */
	public function On() {
		$this->doperf = true;
	}

	/**
	 * Turn Performance logging off
	 */
	public function Off() {
		$this->doperf = false;
	}

	/**
	 * Generate a stamp to the output
	 *
	 * Prints out microtime and memory usage from PHP
	 * Note that the PHP Compiler optimizes this specific code
	 * extremely well. Don't stress about adding lots of calls
	 * to Performace->Stamp(), it won't cause any issues if
	 * $this->doperf is false.
	 *
	 * @param {string} $str The stamp send out
	 * @example "PERF/$str/".microtime()."/".memory_get_usage()."\n"
	 */
	public function Stamp($str, $type = "PERF", $from = false) {
		if (!$this->doperf) {
			return;
		}

		$mem = memory_get_usage();

		// Have we been given something to calculate from?
		if (is_array($from)) {
			$timefrom = $from['now'];
			$memfrom = $from['mem'];
		} else {
			$timefrom = $this->lasttick;
			if ($this->lastmem === false) {
				$this->lastmem = $mem;
			}
			$memfrom = $this->lastmem;
		}

		$now = microtime(); // String. Not float.

		// Let's try to be sensible here. If they don't have the php-bcmath stuff,
		// then don't even bother. It's too hard.
		if (function_exists('bcadd')) {
			// Yay. They do.
			list($msec, $utime) = explode(' ', $now);
			$now = bcadd($msec, $utime, 6);
			if ($timefrom === false) {
				$this->lasttick = $now;
				$timefrom = $now;
			}
			$timediff = bcsub($now, $timefrom, 6);
		} else {
			// No arbitrary precision maths. Don't even try.
			$timediff = "ERROR_INSTALL_PHP-BCMATH";
		}

		$memdiff = $mem - $memfrom;

		$this->lasttick = $now;
		$this->lastmem = $mem;

		print "$type/$str/$now,$timediff/$mem,$memdiff<br/>\n";

		// This is grabbed by Start and Stop.
		return array("now" => $now, "mem" => $mem);
	}

	/**
	 * Start a performance counter
	 *
	 * Prints a timestamp, and records the start time and memory use.
	 */

	public function Start($str = false) {
		if (!$this->doperf) {
			return;
		}

		if (!$str) {
			$str = "Unknown! ".json_encode(debug_backtrace());
		}

		if (isset($this->current[$str])) {
			throw new \Exception("Start was called twice with the same key '$str'");
		}

		$this->current[$str] = $this->Stamp($str, "START");
		return true;
	}

	/**
	 * Stop a performance counter
	 *
	 * Prints a timestamp, and the difference between when it was started and now.
	 * Note that time is not automatically calculated if php-bcmath is not installed,
	 * and will need to be done manually.
	 */

	public function Stop($str = false) {
		if (!$this->doperf) {
			return;
		}

		if (!$str) {
			// array_pop ALWAYS RETURNS the last variable added to an array.
			// Well, it does in this version of php. It may break in another.
			// There is a test for this in framework/utests.
			$start = array_pop($this->current);
		} else {
			if (!isset($this->current[$str])) {
				throw new \Exception("Unable to find START for $str");
			}
			$start = $this->current[$str];
			unset($this->current[$str]);
		}

		$this->Stamp($str, "STOP", $start);
	}
}
