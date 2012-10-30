<?PHP
/*
 Copyright (c) 2011 individual committers of the code
 
 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
 
 Except as contained in this notice, the name(s) of the above copyright holders 
 shall not be used in advertising or otherwise to promote the sale, use or other
 dealings in this Software without prior written authorization.
 
 The end-user documentation included with the redistribution, if any, must 
 include the following acknowledgment: "This product includes software developed 
 by contributors", in the same place and form as other third-party
 acknowledgments. Alternately, this acknowledgment may appear in the software
 itself, in the same form and location as other such third-party
 acknowledgments.
 */

/**
 * SQLite specific result class
 */

class DB_Sqlite_Result extends DB_Result {
	protected $connection = null;
	protected $resource = null;

	/**
	 * Run a query
	 *
	 * @param string $sql
	 * @param resource $connection DB Connection
	 * @throws Exception MySQL error
	 */
	public function __construct($sql, $connection) {
		parent::__construct($sql);
		$this->connection = $connection;
		$errorMessage = ''; // Overwritten by sqlite_query
		$resource = sqlite_query($this->connection, $sql, SQLITE_ASSOC, $errorMessage);

		if (false === $resource) {
			throw new Exception('SQLite Error: ' . $errorMessage);
		} else {
			$this->resource = $resource;
			$this->setNumberOfRows(sqlite_num_rows($resource));
			$this->columns = $this->getColumnTypes();
			$this->rowsAffected = sqlite_changes($this->connection);
			$this->lastId = sqlite_last_insert_rowid($this->connection);
		}
	}


	/**
	 * Return an associative array of converted data
	 *
	 * @return array Associative array of row's data
	 */
	protected function fetchRowAssocDb() {
		$row = sqlite_fetch_array($this->resource, SQLITE_ASSOC);
		$out = array();
		foreach ($row as $k => $v) {
			if (substr($k, 0, 1) == '"' && substr($k, -1) == '"') {
				$k = substr($k, 1, -1);
			}
			$out[$k] = $v;
		}
		return $out;
	}


	/**
	 * Get column information from the result
	 *
	 * @return array Associative array of field => type
	 */
	protected function getColumnTypes() {
		$columns = array();
		for ($i = 0, $max = sqlite_num_fields($this->resource); $i < $max; $i ++) {
			$name = sqlite_field_name($this->resource, $i);
			$columns[$name] = 'mixed';
		}
		return $columns;
	}


	/**
	 * Go to a specified row number
	 *
	 * MySQL will report an error if we go to record 0 when the result
	 * set has 0 records.
	 *
	 * @param mixed $rowNumber
	 * @return $this
	 * @throws Exception
	 */
	protected function goToRow($rowNumber) {
		if ($this->indexMax > 0) {
			if (! sqlite_seek($this->resource, $rowNumber)) {
				throw new Exception('Unable to seek to row ' . $rowNumber);
			}
		}
		return $this;
	}
}
