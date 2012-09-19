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
 * Base class for other DB result classes
 *
 * Defines the required functionality that all database result classes must 
 * implement
 */

abstract class DB_Result implements ArrayAccess, Iterator, Countable {
	protected $columns = array();  // Array of fieldName => dataType
	protected $connection = null;  // DB connection
	protected $index = null;  // Current index (0 <= index < indexMax)
	protected $indexMax = null;  // Highest row number
	protected $keyColumn = null;  // Key column for iterating
	protected $lastId = null;  // Last insert ID generated
	protected $rowData = null;  // Cached data for row at $index
	protected $rowsAffected = null;  // Number of rows affected
	protected $sql = null;  // SQL query to execute

	/**
	 * Run a query
	 *
	 * Throw an exception on error
	 *
	 * If possible, write a desctructor to free the result set
	 *
	 * @param resource $connection DB connection
	 * @param string $sql
	 */
	public function __construct($connection, $sql) {
		$this->connection = $connection;
		$this->sql = $sql;
	}


	/**
	 * The string form of the result set is the SQL query
	 *
	 * This can make debugging much easier.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->sql;
	}


	/**
	 * Returns true if a specified column exists in the result
	 *
	 * @param string $column Column name
	 * @return boolean True if column exists
	 */
	protected function checkColumnExists($column) {
		if (array_key_exists($column, $this->columns)) {
			return true;
		}

		return false;
	}


	/**
	 * Throws exception if we are trying to do some sort of operation on
	 * a query that does not return a result set.
	 *
	 * @throws Exception
	 */
	protected function checkResultSet() {
		if (is_null($this->indexMax)) {
			throw new Exception('Query does not produce a result set - can not perform operation');
		}
	}


	/**
	 * Converts the data in a row
	 *
	 * @param array &$row Row data (passed by reference)
	 */
	protected function convertRow(&$row) {
		foreach ($this->columns as $name => $type) {
			switch ($type) {
				case 'int':
					if (! is_null($row[$name])) {
						$row[$name] = (int) $row[$name];
					}
					break;

				case 'real':
					if (! is_null($row[$name])) {
						$row[$name] = (float) $row[$name];
					}
					break;
				
				// Currently unsure how to handle "date" formats
				case 'datetime':
				case 'timestamp':
					if (! is_null($row[$name])) {
						if ('0000-00-00 00:00:00' === $row[$name]) {
							$row[$name] = null;
						} else {
							$row[$name] = new DateTime($row[$name]);
						}
					}
					break;
			}
		}
	}


	/**
	 * Return the number of rows in the result set
	 *
	 * @return integer Number of rows in the result set
	 * @throws Exception
	 */
	public function count() {
		$this->checkResultSet();
		return $this->indexMax;
	}


	/**
	 * Return the information for the current index
	 *
	 * @return array Row data
	 */
	public function current() {
		$this->checkResultSet();
		return $this->fetchRowAssoc($this->index, true);
	}


	/**
	 * Pull the next record from the set
	 *
	 * @return array Row data as an associative array
	 */
	public function fetch() {
		$this->checkResultSet();
		return $this->fetchRowAssoc();
	}


	/**
	 * Fetch all records as a large array.
	 *
	 * If a column is specified, the value of that column will be used as
	 * the key of the array.  If you specify a non-unique key column, then
	 * it is possible for records to overwrite each other in the resulting
	 * array.
	 *
	 * @param string $keyColumn Column name to use as the key
	 * @return array Array of row arrays
	 */
	public function fetchAll($keyColumn = null) {
		if (! is_null($keyColumn)) {
			$this->checkColumnExists($keyColumn);
		}

		$oldKeyColumn = $this->keyColumn;
		$this->keyColumn = $keyColumn;
		$out = array();

		try {
			foreach ($this as $key => $row) {
				$out[$key] = $row;
			}
		} catch (Exception $ex) {
			$this->keyColumn = $oldKeyColumn;
			throw $ex;
		}

		$this->keyColumn = $oldKeyColumn;
		return $out;
	}


	/**
	 * Fetch all of the values of a column
	 *
	 * This is nearly identical to calling the below code, except that
	 * duplicate values are preserved.
	 *    array_keys($x->fetchAll($column));
	 *
	 * @param string $keyColumn Column name
	 * @return array All of the values from the one column in the result set
	 */
	public function fetchColumn($keyColumn) {
		$this->checkColumnExists($keyColumn);
		$oldKeyColumn = $this->keyColumn;
		$this->keyColumn = $keyColumn;
		$out = array();
		try {
			foreach ($this as $key => $row) {
				$out[] = $row[$column];
			}
		} catch (Exception $ex) {
			$this->keyColumn = $oldKeyColumn;
			throw $ex;
		}
		$this->keyColumn = $oldKeyColumn;
		return $out;
	}


	/**
	 * Fetches a single row associatively and converts data
	 *
	 * Can jump to a specified row.
	 * If desired, will cache row's data into object's memory
	 *
	 * @param integer $rowNumber Row number to jump to
	 * @param boolean $saveRowData If true, cache data as $this->rowData
	 * @return array Row data
	 */
	protected function fetchRowAssoc($rowNumber = null, $saveRowData = false) {
		if (! is_null($rowNumber)) {
			// See if we already have the row in memory
			if (! is_null($this->rowData)) {
				return $this->rowData;
			}

			// Go to the specified row
			$this->goToRow($rowNumber);
		}

		$row = $this->fetchRowAssocDb();
		$this->convertRow($row);

		if ($saveRowData) {
			$this->rowData = $row;
		}

		return $row;
	}


	/**
	 * Fetch a row from the database and return it as an associative array
	 *
	 * @return array Associative array of row's data
	 */
	abstract protected function fetchRowAssocDb();


	/**
	 * Relocate to a row in the data set
	 *
	 * @param mixed $rowNumber
	 * @return $this
	 */
	abstract protected function goToRow($rowNumber);


	/**
	 * Get the key of the current index
	 *
	 * If a key column was set, use the value from that column.  Otherwise,
	 * use the current index.
	 *
	 * @return integer Current index
	 */
	public function key() {
		$this->checkResultSet();

		if (! is_null($this->keyColumn)) {
			$row = $this->fetchRowAssoc($this->index, true);
			return $row[$this->keyColumn];
		}

		return $this->index;
	}


	/**
	 * Return the last insert ID
	 *
	 * Use this function immediately after an insert, otherwise you might
	 * not get the right number.
	 *
	 * @return integer
	 * @throws Exception
	 */
	public function lastId() {
		if (is_null($this->lastId)) {
			throw new Exception('Unable to get last ID - wrong query type');
		}

		return $this->lastId;
	}


	/**
	 * Move the index pointer to the next row
	 *
	 * @return $this
	 */
	public function next() {
		$this->checkResultSet();
		$this->rowData = null;
		$this->index ++;
		return $this;
	}


	/**
	 * Return true if the row exists
	 *
	 * @param mixed $offset Row number
	 * @return bolean
	 */
	public function offsetExists($offset) {
		settype($offset, 'integer');

		if ($offset >= 0 && $offset < $this->indexMax) {
			return true;
		}

		return false;
	}


	/**
	 * Get the data at a specific offset
	 *
	 * @param mixed $offset Row number
	 * @return array Row data as an associative array
	 * @throws Exception
	 */
	public function offsetGet($offset) {
		settype($offset, 'integer');

		if ($offset < 0 || $offset >= $this->indexMax) {
			throw new Exception('Illegal offset for ' . $this->indexMax . ' rows');
		}

		return $this->fetchRowAssoc($offset);
	}


	/**
	 * Set row data at an offset
	 *
	 * @param mixed $offset
	 * @param mixed $data
	 * @throws Exception
	 */
	public function offsetSet($offset, $data) {
		throw new Exception('Not allowed to set data on a result set');
	}


	/**
	 * Unset a row of data in the result set
	 *
	 * @param mixed $offset
	 * @throws Exception
	 */
	public function offsetUnset($offset) {
		throw new Exception('Now allowed to unset data on a result set');
	}


	/**
	 * Rewind the internal pointer back to the first row
	 * 
	 * @return $this
	 */
	public function rewind() {
		$this->checkResultSet();
		$this->rowData = null;
		$this->index = 0;
		return $this;
	}


	/**
	 * Returns the number of rows affected for a non-"result set" result set
	 *
	 * @return integer Number of rows affected
	 * @throws Exception
	 */
	public function rowsAffected() {
		if (is_null($this->rowsAffected)) {
			throw new Exception('Query did not affect rows');
		}

		return $this->rowsAffected;
	}


	/**
	 * Helper for the per-DB constructors to set some required information
	 *
	 * @param integer $max Maximum number of rows
	 * @return $this
	 */
	protected function setNumberOfRows($max) {
		$this->index = 0;
		$this->indexMax = $max;
		return $this;
	}


	/**
	 * Sets the key column, which is used with foreach
	 *
	 * @param string|null $keyColumn Column name to use as the key
	 * @return $this
	 */
	public function setKeyColumn($keyColumn = null) {
		if (! is_null($keyColumn)) {
			$this->checkColumnExists($keyColumn);
		}
		$this->keyColumn = $keyColumn;
		return $this;
	}


	/**
	 * Return true if current index is valid
	 *
	 * @return boolean True if index is valid
	 */
	public function valid() {
		$this->checkResultSet();

		if ($this->index >= 0 && $this->index < $this->indexMax) {
			return true;
		}

		return false;
	}
}
