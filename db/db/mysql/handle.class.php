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

class DB_Mysql_Handle extends DB_Base {
	protected $connection;  // Database connection


	/**
	 * Make the connection to the database
	 *
	 * @param array $components Results of parse_url()
	 * @throws Exception
	 */
	public function __construct($components) {
		parent::__construct($components);

		if ($this->persist && function_exists('mysql_pconnect')) {
			$this->connection = @mysql_pconnect($this->hostPort, $this->username, $this->password);
		} elseif (function_exists('mysql_connect')) {
			$this->connection = @mysql_connect($this->hostPort, $this->username, $this->password);
		} else {
			throw new Exception('PHP does not have MySQL functions enabled');
		}

		if (false === $this->connection) {
			throw new Exception(mysql_error(), mysql_errno());
		}
	}


	/**
	 * Switch to another database
	 *
	 * @param string $db Target database name
	 * @return boolean True on success
	 * @throws Exception
	 */
	protected function dbSwitch($db) {
		$result = mysql_select_db($db, $this->connection);

		if (! $result) {
			throw new Exception(mysql_error($this->connection), mysql_errno($this->connection));
		}

		return $result;
	}


	/**
	 * Escape a single field name
	 *
	 * @param string $field Field name
	 * @return string Escaped version
	 */
	public function fieldName($field) {
		return '`' . $field . '`';
	}


	/**
	 * Generate the query to see if a field exists
	 *
	 * @param string $table Table name
	 * @param string $field Field name
	 * @param mixed $options Additional options (see base's parseOptions())
	 * @return boolean|string True if field exists or SQL string
	 */
	public function fieldExists($table, $field, $options = false) {
		$options = $this->parseOptions($options);
		$sql = 'SHOW COLUMNS FROM ' . $this->tableName($table) . ' LIKE ' . $this->quote($field);

		if (isset($options['sql'])) {
			return $sql;
		}

		$result = $this->query($sql);

		if ($result->rows()) {
			return true;
		}

		return false;
	}


	/**
	 * Return the last autoincrement insert ID
	 * Returns true if no autoincrement ID was employed
	 * 
	 * @return integer|boolean Autoincrement value or true if none was used
	 */
	protected function lastId() {
		$ret = mysql_insert_id($this->connection);

		if (0 === $ret) {
			return true;
		}

		return $ret;
	}


	/**
	 * Restrict the result set to a number of rows or remove the restriction
	 *
	 * @param string $sql Current SQL command
	 * @param integer $max Maximum number of rows
	 * @param integer $offset Starting row number
	 * @return string Modified SQL command
	 */
	public function limit($sql, $max = false, $offset = false) {
		settype($max, 'integer');
		settype($offset, 'integer');

		if ($max < 1) {
			// No limit
			$limit = '';
		} elseif ($offset < 1) {
			$limit = ' LIMIT ' . $max;
		} else {
			$limit = ' LIMIT ' . $offset . ', ' . $max;
		}

		$sql2 = preg_replace('/\\s+limit \\d+\\s*(,\\s*\\d+|offset\\s+\\d+)?\\s*$/i', '', $sql) . $limit;
		return $sql2;
	}


	/**
	 * Escape a table name and probably prefix it
	 *
	 * @param string $table Unescaped table name
	 * @return string Escaped table name
	 */
	public function tableName($table, $prefix = true) {
		if ($prefix) {
			$table = $this->prefix . $table;
		}
		return '`' . $table . '`';
	}


	/**
	 * Return true if the specified table exists in the database
	 *
	 * Available options:
	 *    sql - Return the generated SQL
	 *
	 * @param string $table Name of table
	 * @param mixed $options Additional options
	 * @return boolean True if table exists
	 */
	public function tableExists($table, $options = false) {
		$options = $this->parseOptions($options);
		$sql = 'SHOW TABLES LIKE ' . $this->quote($table);

		if (isset($options['sql'])) {
			return $sql;
		}

		$result = $this->query($sql);

		if ($result->rows()) {
			return true;
		}

		return false;
	}
}
