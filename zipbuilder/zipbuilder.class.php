<?php
/* Zip file creation class
 * Makes zip files, can build them mostly in memory when using actual files.
 *
 * Quick usage:
 *
 *   $builder = new ZipBuilder();
 *   $builder->addFile('README.txt', '/tmp/README.txt');
 *   $builder->addDir('docs');
 *   $builder->addFileData('docs/LICENSE', 'This has no license');
 *   $builder->outputToBrowser('target.zip');
 *
 * Based on the following works:
 *
 *  http://phpmyadmin.sourceforge.net/
 *  The libraries/zip.lib.php3 file
 * 
 *  http://www.zend.com/codex.php3?id=535&single=1
 *  By Eric Mueller <eric@themepark.com>
 * 
 *  http://www.zend.com/codex.php3?id=470&single=1
 *  by Denis125 <webmaster@atlant.ru>
 * 
 *  a patch from Peter Listiak <mlady@users.sourceforge.net> for last modified
 *  date and time of the compressed file
 *
 *  http://www.pkware.com/appnote.txt
 *  Official ZIP file format
 *
 *  Many other minor changes by Tyler Akins to make it more correct.
 *
 * If you plan on sending this to the browser, make sure that you do not
 * have zlib output compression turned on.  If you do have it turned on for
 * your site, you need to turn it off for a directory (the one that your PHP
 * script runs in) with a .htaccess file.
 *    php_flag zlib.output_compression off
 * Otherwise the output may be compressed again and some browsers (specifically
 * Internet Explorer 6) will see the extra 25 bytes that are added by this
 * extra compression and that will make the zipfile corrupt.
 */
class ZipBuilder {
	protected $entries = array();

	/* Adds a directory/folder to the archive
	 * 
	 * @param string $name Directory name as it appears in the archive
	 * @param integer $timestamp Directory timestamp (optional)
	 */
	public function addDir($name, $time = null) {
		$name = str_replace('\\', '/', $name);

		if (substr($name, -1) !== '/') {
			$name .= '/';
		}

		$this->entries[] = array(
			'type' => 'directory',
			'name' => $name,
			'timestamp' => $time
		);
	}


	/**
	 * Adds a file to the archive
	 *
	 * Using this method is preferred to save you memory
	 *
	 * @param string $name Filename in archive - may contain the path
	 * @param string $realName Actual filename on disk
	 * @param integer $timestamp Override file timestamp (Unix timestamp, optional)
	 */
	public function addFile($name, $realName = null, $time = null) {
		if ($realName === null) {
			$realName = $name;
		}

		$name = str_replace('\\', '/', $name);
		
		$this->entries[] = array(
			'type' => 'file',
			'name' => $name,
			'realName' => $realName,
			'timestamp' => $time
		);
	}


	/**
	 * Load a file on the fly
	 *
	 * Used when compressing a file added with addFile() and we are generating
	 * the zip file.  Using addFile() will consume far less memory, and is
	 * very useful for generating large zip files.
	 *
	 * @param array $entry
	 * @return array Modified $entry
	 */
	protected function loadFile($entry) {
		$data = file_get_contents($entry['realName']);

		if (! $entry['timestamp']) {
			$entry['timestamp'] = filemtime($entry['realName']);
		}

		$compressedData = gzdeflate($data, 9);
		$out = array(
			'type' => 'data',
			'name' => $entry['name'],
			'timestamp' => $entry['timestamp'],
			'originalSize' => strlen($data),
			'originalCrc' => crc32($data),
			'compressedData' => $compressedData,
		);
		return $out;
	}


	/**
	 * Adds a file's data to the archive
	 *
	 * Extremely similar to using
	 *    $builder->addFile($name, file_get_contents($filename),
	 *        filemtime($filename));
	 * Except that this method loads the file contents into memory where as
	 * addFile() will not keep the file's contents in memory at all times.
	 *
	 * @param string $name Filename - may contain the path
	 * @param string $data Data contained in the file
	 * @param integer $timestamp File timestamp (Unix timestamp, optional)
	 */
	public function addFileData($data, $name, $time = null) {
		$name = str_replace('\\', '/', $name);
		$compressedData = gzdeflate($data, 9);

		$this->entries[] = array(
			'type' => 'data',
			'name' => $name,
			'timestamp' => $time,
			'originalSize' => strlen($data),
			'originalCrc' => crc32($data),
			'compressedData' => $compressedData,
		);
	}


	/**
	 * Writes headers for downloading this file directly
	 *
	 * @param string $zipName Name of zip file to download
	 */
	public function outputToBrowserHeaders($zipName) {
		// Enable caching
		header('Pragma: ');
		header('Cache-Control: cache');

		// Check if we are IE6 - it requires special headers
		$badIE = 0;
		$ua = '';
		if (! empty($_SERVER['HTTP_USER_AGENT'])) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}

		if (strstr($ua, 'compatible; MSIE ') !== false && strstr($ua, 'Opera') === false && strstr($ua, 'compatible; MSIE 6') === false) {
			$badIE = 1;
		}
		
		$zipName = preg_replace('/[^-a-zA-Z0-9\.]/', '_', $zipName);
		
		if ($badIE) {
			header("Content-Disposition: inline; filename=$zipName");
			header("Content-Type: application/zip; name=\"$zipName\"");
		} else {
			header("Content-Disposition: attachment; filename=\"$zipName\"");
			header("Content-Type: application/zip; name=\"$zipName\"");
		}
	}


	/**
	 * Write the file out to a browser
	 *
	 * @param string $filename
	 * @param boolean $outputHeaders If true, sends headers (optional)
	 */
	public function outputToBrowser($filename, $outputHeaders = true) {
		if ($outputHeaders) {
			if (! headers_sent()) {
				$this->outputToBrowserHeaders($filename);
			}
		}

		$this->write(function ($data) {
			echo $data;
		});
	}


	/**
	 * Convert a Unix timestamp to a four-byte DOS date and time format.
	 * Date is in the high two bytes, time is in the lower two bytes.
	 * You may notice that DOS doesn't care about every other second (the
	 * 1's bit on the second is removed).
	 *
	 * @param integer $unixTime Unix timestamp or null for current time
	 * @return integer Big big number for DOS time
	 */
	protected function unix2DosTime($unixTime = null) {
		if ($unixTime == 0) {
			$timeArray = getdate();
		} else {
			$timeArray = getdate($unixTime);
		}

		if ($timeArray['year'] < 1980) {
			$timeArray = array(
				'year' => 1980,
				'mon' => 1,
				'mday' => 1,
				'hours' => 0,
				'minutes' => 0,
				'seconds' => 0
			);
		}


		// Bits:  YYYY YYYM MMMD DDDD  HHHH HMMM MMSS SSSS
		//   Y = Years from 1980
		//   M = Month (1 - 12)
		//   D = Day (1 - 31)
		//   H = Hour (24 hour)
		//   M = Minute (0 - 60)
		//   S = Seconds (actually seconds >> 1 to drop the odd second bit)
		$ymd = ($timeArray['year'] - 1980) << 9;
		$ymd += $timeArray['mon'] << 5;
		$ymd += $timeArray['mday'];
		$hms = $timeArray['hours'] << 11;
		$hms += $timeArray['minutes'] << 6;
		$hms += $timeArray['seconds'] >> 1;
		return pack('v', $hms) . pack('v', $ymd);
	}


	/**
	 * Output a generated zip file
	 *
	 * @param callable $callback Where to send the generated data
	 */
	public function write($callback) {
		$directory = array();
		$offset = 0;

		$writeChunk = function ($data) use ($callback, &$offset) {
			$offset += strlen($data);
			$callback($data);
		};

		foreach ($this->entries as $entry) {
			switch ($entry['type']) {
				case 'data':
					$directory[] = $this->writeData($entry, $offset, $writeChunk);
					break;

				case 'directory':
					$directory[] = $this->writeDir($entry, $offset, $writeChunk);
					break;

				case 'file':
					$entry = $this->loadFile($entry);
					$directory[] = $this->writeData($entry, $offset, $writeChunk);
					break;


				default:
					throw new Exception('Unhandled entry type: ' . $entry['type']);
			}
		}

		$footer = implode('', $directory);  // Control directory
		$directoryLength = strlen($footer);
		$footer .= "\x50\x4b\x05\x06\x00\x00\x00\x00";  // EOF Control Directory
		$footer .= pack('v', count($directory));  // Number of entries on this disk
		$footer .= pack('v', count($directory));  // Total number of entries
		$footer .= pack('V', $directoryLength);  // Size of central directory
		$footer .= pack('V', $offset);  // Offset to start of central directory
		$footer .= "\x00\x00"; // Comment length
		$callback($footer);
	}


	/**
	 * Writes a file entry (already compressed in memory) to the archive
	 *
	 * @param array $entry Entry added with addFileData()
	 * @param integer $offset Offset to start of this record
	 * @param callable $dataCallback Where to send the generated data
	 * @return string Central directory record
	 */
	protected function writeData($entry, $offset, $dataCallback) {
		$data = "\x50\x4b\x03\x04";
		$data .= "\x14\x00";  // Version needed to extract.  Should be 0x1400 but for compatability, we could use 0x0A00
		$data .= "\x00\x00";  // General purpose bit flag
		$data .= "\x08\x00";  // Compression method
		$data .= $this->unix2DosTime($entry['timestamp']);  // Last modification date and time

		// Local file header segment
		$data .= pack('V', $entry['originalCrc']);  // CRC32
		$data .= pack('V', strlen($entry['compressedData']));  // Compressed filesize
		$data .= pack('V', $entry['originalSize']);  // Uncompressed filesize
		$data .= pack('v', strlen($entry['name']));  // Length of filename
		$data .= pack('v', 0);  // Extra field length
		$data .= $entry['name'];  // Filename
		
		// File data segment
		$data .= $entry['compressedData'];
		$dataCallback($data);
		
		// now add to central directory record
		$directory = "\x50\x4b\x01\x02";
		$directory .= "\x00\x00";  // Version made by
		$directory .= "\x14\x00";  // Version needed to extract
		$directory .= "\x00\x00";  // General purpose bit flag
		$directory .= "\x08\x00";  // Compression method
		$directory .= $this->unix2DosTime($entry['timestamp']);  // Last modification date and time
		$directory .= pack('V', $entry['originalCrc']);  // CRC32
		$directory .= pack('V', strlen($entry['compressedData']));  // Compressed filesize
		$directory .= pack('V', $entry['originalSize']);  // Uncompressed filesize
		$directory .= pack('v', strlen($entry['name']));  // Length of filename
		$directory .= pack('v', 0);  // Extra field length
		$directory .= pack('v', 0);  // File comment length
		$directory .= pack('v', 0);  // Disk number start
		$directory .= pack('v', 0);  // Internal file attributes
		$directory .= pack('V', 32);  // External file attributes - archive bit is set
		$directory .= pack('V', $offset);  // Relative offset of local header
		$directory .= $entry['name'];
		
		// optional extra field, file comment goes here
		return $directory;
	}


	/**
	 * Writes a directory entry to the archive
	 *
	 * @param array $entry Entry added with addDir()
	 * @param integer $offset Offset to start of this record
	 * @param callable $dataCallback Where to send the generated data
	 * @return string Central directory record
	 */
	protected function writeDir($entry, $offset, $dataCallback) {
		$data = "\x50\x4b\x03\x04";
		$data .= "\x0a\x00";
		$data .= "\x00\x00";
		$data .= "\x00\x00";
		$data .= $this->unix2DosTime($entry['timestamp']);
		$data .= pack('V', 0);
		$data .= pack('V', 0);
		$data .= pack('V', 0);
		$data .= pack('v', strlen($entry['name']));
		$data .= pack('v', 0);
		$data .= $entry['name'];
		$data .= pack('V', 0);
		$data .= pack('V', 0);
		$data .= pack('V', 0);
		$dataCallback($data);  // Also updates $offset
		$directory = "\x50\x4b\x01\x02";
		$directory .= "\x00\x00";
		$directory .= "\x0a\x00";
		$directory .= "\x00\x00";
		$directory .= "\x00\x00";
		$directory .= "\x00\x00\x00\x00";
		$directory .= pack('V', 0);
		$directory .= pack('V', 0);
		$directory .= pack('V', 0);
		$directory .= pack('v', strlen($entry['name']));
		$directory .= pack('v', 0);
		$directory .= pack('v', 0);
		$directory .= pack('v', 0);
		$directory .= pack('v', 0);
		$directory .= pack('V', 16);
		$directory .= pack('V', $offset);
		$directory .= $entry['name'];
		return $directory;
	}
}
