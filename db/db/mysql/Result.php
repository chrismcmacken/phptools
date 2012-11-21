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
 * MySQL specific result class
 */

class DB_Mysql_Result extends DB_Result {
	protected $connection = null;
	protected $resource = null;

	/**
	 * Run a query
	 *
	 * @param resource $connection DB Connection
	 * @param string $sql
	 * @throws Exception MySQL error
	 */
	public function __construct($sql, $connection) {
		parent::__construct($sql);
		$this->connection = $connection;
		$resource = mysql_query($sql, $this->connection);

		if (false === $resource) {
			$this->throwError();
		} elseif (! is_bool($resource)) {
			$this->resource = $resource;
			$this->setNumberOfRows(mysql_num_rows($resource));
			$this->columns = $this->getColumnTypes();
		} else {
			$this->rowsAffected = mysql_affected_rows($this->connection);
			$this->lastId = mysql_insert_id($this->connection);
		}
	}


	/**
	 * Free the resource
	 */
	public function __destruct() {
		if (! is_null($this->resource)) {
			mysql_free_result($this->resource);
		}
	}


	/**
	 * Return an associative array of converted data
	 *
	 * @return array Associative array of row's data
	 */
	protected function fetchRowAssocDb() {
		$row = mysql_fetch_assoc($this->resource);
		if (mysql_errno($this->connection)) {
			$this->throwError();
		}
		return $row;
	}


	/**
	 * Get column information from the result
	 *
	 * @return array Associative array of field => type
	 */
	protected function getColumnTypes() {
		$columns = array();
		for ($i = 0, $max = mysql_num_fields($this->resource); $i < $max; $i ++) {
			$type = mysql_field_type($this->resource, $i);
			$name = mysql_field_name($this->resource, $i);
			$columns[$name] = $type;
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
			if (! mysql_data_seek($this->resource, $rowNumber)) {
				throw new Exception('Unable to seek to row ' . $rowNumber);
			}
		}
		return $this;
	}


	/**
	 * Throw an error exception
	 *
	 * @throws Exception
	 */
	protected function throwError() {
		throw new Exception('MySQL Error:  ' . mysql_error($this->connection), mysql_errno());
	}
}
