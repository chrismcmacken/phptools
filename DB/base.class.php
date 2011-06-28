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
 * Base class for other DB interfaces
 *
 * Defines the required functionality that all database classes must implement
 */

abstract class DB_Base {
	protected $db;  // The current database, if known
	protected $hostPort;  // The host or host+port
	static protected $lastDb = array();  // Work around PHP connection reuse
	protected $lastDbKey;  // Unique key between persisted connections
	protected $options;  // Options to use with this connection
	protected $password;  // Password for the connection
	protected $persist = false;  // Try to use a persistent connection?
	protected $prefix;  // Prefix on the table name, if any
	protected $scheme;  // Scheme (eg. mysql)
	protected $username;  // Username for the connection

	/**
	 * Set up the basic variables for the DB class.
	 * Do not actually connect to the database.
	 *
	 * @param array $components The results from parse_url()
	 */
	public function __construct($components) {
		$this->db = substr($components['path'], 1);  // Remove leading slash
		$this->hostPort = $components['host'];
		$this->password = $components['pass'];
		$this->prefix = $components['fragment'];
		$this->scheme = $components['scheme'];
		$this->username = $components['user'];

		if (! empty($components['port'])) {
			$this->hostPort .= ':' . $components['port'];
		}

		// Handle options
		parse_str($components['query'], $options);

		if (array_key_exists('persist', $options)) {
			$this->persist = true;
		}

		// Keep a list of the last database selected for each host.
		$this->lastDbKey = $this->dbKey();

		if (! isset($this->lastDb[$this->lastDbKey])) {
			$this->lastDb[$this->lastDbKey] = '';
		}
	}


	/**
	 * Signify a switch of databases.  Does not actually change databases.
	 * Instead, that is done at the beginning of query() with
	 * dbSwitchIfNeeded().
	 *
	 * USE [database_name]
	 *
	 * @param string $db Database name
	 */
	public function db($db) {
		$this->db = $db;
		return $this->dbSwitchIfNeeded();
	}


	/**
	 * Generates a unique key that will be the same if two DB connections
	 * might use the same persisted connection.
	 * 
	 * Often, PHP reuses connections even in the same thread as long as the
	 * host + port + username + password are the same.  This means
	 * you might have two DB_* objects and you think they are in
	 * separate databases, but it turns out that PHP is smarter than you
	 * and is reusing the connection to the server for both of these
	 * objects.  So, we need to step up our aggressive tactics a notch.
	 *
	 * This function will return a string that will be the same when two
	 * DB objects might share connections.  This key is used to determine
	 * if we need to issue 'USE' statements.
	 *
	 * @return string Some sort of key
	 */
	protected function dbKey() {
		$parts = array(
			$this->scheme,
			$this->username,
			$this->password,
			$this->hostPort
		);
		return md5(implode('|', $parts));
	}


	/**
	 * Actually performs the switch of the database.  A little function
	 * so that one doesn't need to override dbSwitchIfNeeded().
	 *
	 * This MUST NOT use query().
	 *
	 * @param string $db Target database name
	 * @return boolean True on success
	 */
	abstract protected function dbSwitch($db);


	/**
	 * Perform a check to see if we need to switch databases with this
	 * particular connection.
	 *
	 * @return boolean True on success
	 */
	protected function dbSwitchIfNeeded() {
		if ($this->lastDb[$this->lastDbKey] !== $this->db) {
			$this->lastDb[$this->lastDbKey] = $this->db;
			return $this->dbSwitch($this->db);
		}

		return true;
	}


	/**
	 * Delete records from the database.
	 *
	 * Careful when you don't pass in a $where condition
	 *
	 * Supported options:
	 *    sql - Return the generated SQL string
	 *
	 * @param string $table Table name
	 * @param mixed $where Where conditions
	 * @param mixed $options Additional options
	 * @return integer|string Number of rows affected or SQL string
	 */
	public function delete($table, $where = false, $options = false) {
		$options = $this->parseOptions($options);
		$sql = 'DELETE FROM ' . $this->tableName($table);
		
		if ($where) {
			$sql .= ' WHERE ' . $this->where($where);
		}

		if (isset($options['sql'])) {
			return $sql;
		}

		$result = $this->query($sql);
		return $result->rowsAffected();
	}


	/**
	 * Remove a table from the database
	 *
	 * Supported options:
	 *    sql - Return the generated SQL string
	 *
	 * @param string $table Table name
	 * @param mixed $options Additional options
	 * @return boolean|string True on success or SQL string
	 */
	public function dropTable($table, $options = false) {
		$options = $this->parseOptions($options);
		$sql = 'DROP TABLE ' . $this->tableName($table);

		if (isset($options['sql'])) {
			return $sql;
		}

		return $this->query($sql);
	}


	/**
	 * Escape a field name or a table+field name
	 *
	 * Tables and fields are separated by a period.  Just don't name your
	 * table with a period in it and we'll be fine.  Also, don't be doing
	 * cross-database joins.
	 *
	 * @param string $field Field or Table + Field
	 * @return string Escaped version
	 */
	public function field($field) {
		$fieldExploded = explode('.', $field, 2);
		$field = $this->fieldName(array_pop($fieldExploded));
		if (count($fieldExploded)) {
			$field = $this->tableName(array_pop($fieldExploded)) . '.' . $field;
		}
		return $field;
	}


	/**
	 * Overrideable function to escape just a single field name
	 *
	 * @param string $field Field name
	 * @return string Escaped field name
	 */
	protected function fieldName($field) {
		return $field;
	}


	/**
	 * Return true if a named field exists
	 *
	 * @param string $table Table name
	 * @param string $field Field name
	 * @param mixed $options Additional options
	 * @return boolean|string True if field exists or generated SQL
	 */
	abstract public function fieldExists($table, $field);


	/**
	 * Return an instance of the DB_Fluent class so SQL can be generated
	 * with a fluent interface.
	 *
	 * @return DB_Fluent
	 */
	public function fluent() {
		return new DB_Fluent($this);
	}


	/**
	 * Generate and execute a bulk INSERT INTO statement
	 *
	 * Supported options:
	 *    sql - Return generated code
	 *
	 * @param string $table Table name
	 * @param array $dataArray array of Associative arrays (fieldName => value)
	 * @param mixed $options Additional options
	 * @return mixed True/false for success or generated SQL
	 */
    public function insertBulk($table, $dataArray, $options = false) {
        $options = $this->parseOptions($options);
		$fields = array();
        $firstArray = reset($dataArray);
		foreach ($firstArray as $k => $v) {
			$fields[] = $this->fieldName($k);
		}
		$sql = 'INSERT INTO ' . $this->tableName($table) . ' (';
		$sql .= implode(', ', $fields) . ') VALUES';

        $insertArray = array();
        //build our bulk query
        foreach($dataArray as $data) {
            $values = array();
            foreach($data as $value) {
                $values[] = $this->toSqlValue($value);
            }

            $insertArray[] = '(' . implode(', ', $values) . ')';
        }

        $sql .= ' ' . implode(', ', $insertArray);

        if(! empty($options['sql'])) {
            return $sql;
        }

        $result = $this->query($sql);
        if(! $result) {
            return false;
        }

        return $result;
    }


	/**
	 * Generate and execute an INSERT statement
	 *
	 * Supported options:
	 *    sql - Return generated code
	 *
	 * @param string $table Table name
	 * @param array $what Associative array (fieldName => value)
	 * @param mixed $options Additional options
	 * @return mixed Last insert ID or True/false for success or generated SQL
	 */
	public function insert($table, $what, $options = false) {
		$options = $this->parseOptions($options);
		$fields = array();
		$values = array();
		foreach ($what as $k => $v) {
			$fields[] = $this->fieldName($k);
			$values[] = $this->toSqlValue($v);
		}
		$sql = 'INSERT INTO ' . $this->tableName($table) . ' (';
		$sql .= implode(', ', $fields) . ') VALUES (';
		$sql .= implode(', ', $values) . ')';

		if (! empty($options['sql'])) {
			return $sql;
		}

		$result = $this->query($sql);
		if (! $result) {
			return false;
		}
		return $result->lastId();
	}


	/**
	 * Returns true if the SQL passed in contains a comparison
	 *
	 * @param string $sql
	 * @return boolean True if this looks like a comparison
	 */
	protected function isComparison($sql) {
		return (bool) preg_match('/(<|>|=| LIKE | IN )/i', $sql);
	}


	/**
	 * Returns true if the SQL passed in looks like a field,
	 * table.field, or database.table.field.  Does not actually check
	 * if the field exists.
	 *
	 * @param string $sql
	 * @return boolean True if this looks like a field
	 */
	protected function isField($sql) {
		// Look for a function call or math
		return (! (bool) preg_match('/[ \\(\\+\\-\\*\\/]/', $sql));
	}


	/**
	 * Return the last autoincrement insert ID
	 * Returns true if no autoincrement ID was employed
	 *
	 * @return integer|boolean Autoincrement value or true if none was used
	 */
	abstract protected function lastId();  // Override this so insert() doesn't need overriding


	/**
	 * Restrict the result set to a number of rows or remove the restriction
	 *
	 * @param string $sql Current SQL command
	 * @param integer $max Maximum number of rows
	 * @param integer $offset Starting row number
	 * @return string Modified SQL command
	 */
	abstract protected function limit($sql, $max = null, $offset = null);


	/**
	 * Change the incoming options into an associative array
	 *
	 * @param mixed $options Unparsed options
	 * @return array Reformatted options
	 */
	protected function parseOptions($options) {
		$out = array();

		if (is_string($options)) {
			$out[$options] = true;
		} elseif (is_array($options)) {
			foreach ($options as $k => $v) {
				if (is_numeric($k)) {
					$out[$v] = true;
				} else {
					$out[$k] = $v;
				}
			}
		}

		return $out;
	}


	/**
	 * Execute a single SQL query
	 *
	 * Available options:
	 *    one - Add a limit of 1 and return the row (not the result object)
	 *    limit - Add an arbitrary limit
	 *    offset - If using a limit, start the offset at the given number
	 *    sql - Return SQL string
	 *
	 * @param string $sql
	 * @return boolean True on success
	 */
	public function query($sql, $options = null) {
		$options = $this->parseOptions($options);

		if (isset($options['one'])) {
			$options['limit'] = null;
			$options['offset'] = null;
			$sql = $this->limit($sql, 1);
		}

		if (! empty($options['limit'])) {
			if (! empty($options['offset'])) {
				$sql = $this->limit($sql, $options['limit'], $options['offset']);
			} else {
				$sql = $this->limit($sql, $options['limit']);
			}
		}

		if (isset($options['sql'])) {
			return $sql;
		}

		$this->dbSwitchIfNeeded();
		$resultClass = get_class($this) . '_Result';
		$result = new $resultClass($this->connection, $sql);
		
		if (isset($options['one'])) {
			return $result->fetch();
		}

		return $result;
	}


	/**
	 * Escape and quote a string
	 *
	 * @param string $in String to quote
	 * @return string Quoted string with backslash goodness
	 */
	public function quote($in) {
		return '"' . addslashes($in) . '"';
	}


	/**
	 * Build a select statement
	 *
	 * When referenced in the comment below, "table/field" means either a
	 * single field name (e.g. firstName) or a table name and field name
	 * (e.g. Customer.firstName).
	 *
	 * $fields can be
	 *    False, null, empty array, or empty string for nothing
	 *    A string containing a single table/field
	 *    A string containing SQL
	 *    An array that contains
	 *       Numeric keys with the table/field as the value
	 *       Numeric keys with SQL as the value
	 *       Table/field as the key with the alias ("AS") as the value
	 *       SQL as the key with the alias ("AS") as the value
	 * $from can be
	 *    False, null, empty array, or empty string for nothing
	 *    A string containing a single field name
	 *    An array that contains
	 *       Numeric keys with the table name (must do first table this way)
	 *       Numeric keys with the an array of data specifying a join
	 *          as => Aliasing the table (optional)
	 *          join => Join type (optional, defaults to "inner")
	 *          on => Where criteria for the join
	 *          table => Name of table (if key is numeric)
	 *       Table names as the key with an array of data specifying a join
	 *          as => Aliasing the table (optional)
	 *          join => Join type (optional, defaults to "inner")
	 *          on => Where criteria for the join (see $where)
	 * $where can be
	 *    False, null, empty array, or empty string for nothing
	 *    A SQL string
	 *    An array that contains
	 *       Numeric keys with SQL as the value
	 *       Table/field with expected data as the value
	 *       Table/field with DB operators as the value
	 * $order can be
	 *    False, null, empty array, or empty string for nothing
	 *    A string containing a single table/field
	 *    A string containing SQL
	 *    An array of fields to be sorted, consisting of
	 *       Numeric keys with table/field as the value (sorted ascending)
	 *       Numeric keys with SQL as the value
	 *       Table/field as the key and the value will determine the sort
	 *          (sorted descending if negative, ascending otherwise)
	 *       SQL as the key and the value will determine the sort
	 * $options can be
	 *    False, null, empty array, or empty string for nothing
	 *    A string containing a single option
	 *    An array of options, consisting of
	 *       Numeric keys with the option as the value
	 *       The option string as the key; the value will be potentially used
	 * $group can be
	 *    False, null, empty array, or empty string for nothing
	 *    A string containing a single table/field
	 *    A string containing the SQL
	 *    An array of fields to be sorted, consisting of
	 *       Numeric keys with table/field as the value
	 *       Numeric keys with SQL as the value
	 *       Table/field as the key and the value will not be used
	 * $having can be
	 *    False, null, empty array, or empty string for nothing
	 *    A SQL string
	 *    An array that contains
	 *       Numeric keys with SQL as the value
	 *       Table/field with expected data as the value
	 *       Table/field with DB operators as the value
	 *
	 * Available options:
	 *    distinct - Add DISTINCT to the query
	 *    limit - Set a maximum number of records to return
	 *    offset - If limit is set, start the limit at this offset
	 *    one - Limit the result to 1 and return the record directly
	 *    sql - Return the generated SQL query
	 *
	 * @param mixed $fields (table.)field name or array (per above)
	 * @param mixed $from Table or array of tables and joins
	 * @param mixed $where Single condition or array of conditions
	 * @param mixed $order Single field or array of fields for ordering
	 * @param mixed $options Additional options
	 * @param mixed $group Single grouping condition or array of conditions
	 * @param mixed $having Single where clause or array of clauses
	 * @return mixed Results object or SQL string
	 */
	public function select($fields, $from = false, $where = false, $order = false, $options = false, $group = false, $having = false) {
		$sql = 'SELECT';

		if (! empty($options['distinct'])) {
			$sql .= ' DISTINCT';
		}

		// Fields
		$fields = $this->selectFieldList($fields);
		$fieldsSql = array();

		if (! $fields) {
			throw new Exception('Must specify the "from" of a select query');
		}

		foreach ($fields as $k => $v) {
			if (true === $v) {
				$fieldsSql[] = $k;
			} else {
				$fieldsSql[] = $k . ' AS ' . $this->field($v);
			}
		}

		$sql .= ' ' . implode(', ', $fieldsSql);

		// From
		$fromTables = $this->selectFrom($from);
		
		if ($fromTables) {
			$from = array_shift($fromTables);
			$from['join'] = '';
			array_unshift($fromTables, $from);
			$sql .= ' FROM ';

			foreach ($fromTables as $tableDef) {
				if (! empty($tableDef['join'])) {
					$sql .= ' ' . $tableDef['join'] . ' JOIN ';
				}

				$sql .= $this->tableName($tableDef['table']);

				if (! empty($tableDef['as'])) {
					$sql .= ' AS ' . $this->tableName($tableDef['as'], false);
				}

				if (! empty($tableDef['on'])) {
					$sql .= ' ON ' . $this->where($tableDef['on']);
				}
			}
		}

		// Where
		$where = $this->where($where);

		if (! empty($where)) {
			$sql .= ' WHERE ' . $where;
		}
		
		// Order
		$order = $this->selectFieldList($order);
		
		if ($order) {
			$orderSql = array();

			foreach ($order as $k => $v) {
				if ($v < 0) {
					$orderSql[] = $k . ' DESC';
				} else {
					$orderSql[] = $k . ' ASC';
				}
			}

			$sql .= ' ORDER BY ' . implode(', ', $orderSql);
		}

		// Group
		$group = $this->selectFieldList($group);

		if ($group) {
			$sql .= ' GROUP BY ' . implode(', ', array_keys($group));
		}
		
		// Having
		$having = $this->where($having);

		if (! empty($having)) {
			if (! empty($having)) {
				$sql .= ' HAVING ' . $where;
			}
		}

		// Options
		return $this->query($sql, $options);
	}


	/**
	 * Convert the incoming string/array into a consistent format for
	 * processing by select().  For the format of the incoming array, see
	 * the comment in front of select().  The field names in the returned
	 * data will be escaped properly
	 *
	 * @param mixed $def Table definitions
	 * @return array Consistently formatted field arrays
	 */
	protected function selectFieldList($def) {
		$out = array();

		if (! $def) {
			return array();
		}

		if (! is_array($def)) {
			return array(
				$def => true,
			);
		}

		foreach ($def as $k => $v) {
			if (is_numeric($k)) {
				$k = $v;
				$v = true;
			}

			if ($this->isField($k)) {
				$k = $this->field($k);  // Might be Table.Field, so do not use ->fieldName();
			}

			$out[$k] = $v;
		}

		return $out;
	}


	/**
	 * Convert the incoming string/array into a consistent format for
	 * processing by select().  For the format of the incoming array, see the
	 * comment in front of select().
	 *
	 * @param mixed $def Table definitions
	 * @return array Consistently formatted table arrays
	 */
	protected function selectFrom($def) {
		$out = array();

		if (! $def) {
			return array();
		}

		if (! is_array($def)) {
			$def = array(
				array(
					'table' => $def,
				),
			);
		}

		$v = array_shift($def);  // Get the base table

		if (! is_array($v)) {
			$v = array(
				'table' => $v,
			);
		}

		$out[] = $v;

		foreach ($def as $k => $v) {
			if (! is_array($v)) {
				// If not an array, $v is join conditions
				$v = array(
					'on' => $v,
				);
			}

			if (! is_numeric($k)) {
				// $k is a table name
				$v['table'] = $k;
			}

			if (! isset($v['join'])) {
				$v['join'] = 'INNER';
			}

			$out[] = $v;
		}

		return $out;
	}


	/**
	 * Escape a table name and probably prefixes it
	 *
	 * @param string $table Unescaped table name
	 * @return string Escaped table name
	 */
	public function tableName($table, $prefix = true) {
		if ($prefix) {
			$table = $this->prefix . $table;
		}
		return $table;
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
	abstract public function tableExists($table, $options = false);


	/**
	 * Convert an incoming value into its SQL equivalent
	 *
	 * @param mixed $value Thing to convert
	 * @return string SQL-ified version
	 */
	protected function toSqlValue($value, $key = null) {
		$prefix = '';

		if (! is_null($key)) {
			$prefix = $key . ' = ';
		}

		if (is_null($value)) {
			return $prefix . 'NULL';
		}

		if (is_bool($value)) {
			if ($value) {
				return $prefix . 'TRUE';
			}
			return $prefix . 'FALSE';
		}

		if (is_int($value) || is_float($value)) {
			return $prefix . $value;
		}

		if (is_object($value)) {
			if ($value instanceOf DB_Fragment) {
				// No prefix on this
				return $this->sqlFromFragment($value, $key);
			}
			return $prefix . $this->quote($value->__toString());
		}

		return $prefix . $this->quote($value);
	}


	protected function sqlFromFragment($fragment, $key) {
		$prefixDefault = '';
	
		// Handle all fragments that can be used as a comparison
		if (! is_null($key)) {
			$prefixDefault = $key . ' = ';

			switch ($fragment->getType()) {
				case DB_FRAGMENT_NOW:
					return $prefixDefault . 'NOW()';

				default:
					throw new Exception('Fragment ' . $fragment . ' is not allowed to be used as a comparison.');
			}
		}

		/* Handle all fragments that either build WHERE clauses without it
		 * being in a comparison (eg. "any") and all fragments that can be
		 * used as part of an inserted value.
		 */
		switch ($fragment->getType()) {
			case DB_FRAGMENT_ANY:
				$args = $fragment->getArgs();
				if (! count($args)) {
					throw new Exception($fragment . ' must have arguments');
				}
				foreach ($args as $k => $v) {
					$args[$k] = $this->where($v);
				}
				return '(' . implode(') OR (', $args) . ')';

			case DB_FRAGMENT_NOW:
				return 'NOW()';
			
			default:
				throw new Exception('Fragment ' . $fragment . ' is not allowed to be used stand-alone.');
		}

		throw new Exception('Unhandled fragment: ' . $fragment->getType());
	}


	/**
	 * Generate and execute an UPDATE statement
	 *
	 * Supported options:
	 *    sql - Return generated code
	 *
	 * @param string $table Table name
	 * @param array $what Associative array (fieldName => value)
	 * @param mixed $where Single condition or array of conditions
	 * @param mixed $options Additional options
	 * @return mixed Number of rows affected or false on error
	 */
	public function update($table, $what, $where = null, $options = false) {
		$options = $this->parseOptions($options);
		
		if (is_array($what)) {
			if (! count($what)) {
				throw new Exception('Must have elements in array for fields to update');
			}

			$whatArray = array();

			foreach ($what as $k => $v) {
				$whatArray[] = $this->fieldName($k) . ' = ' . $this->toSqlValue($v);
			}

			$what = implode(', ', $whatArray);
		}

		$sql = 'UPDATE ' . $this->tableName($table) . ' SET ' . $what;

		if (! empty($where)) {
			$sql .= ' WHERE ' . $this->where($where);
		}

		if (! empty($options['sql'])) {
			return $sql;
		}

		$result = $this->query($sql);

		if (! $result) {
			return false;
		}

		return $result->rowsAffected();
	}


	/**
	 * Turn a single condition or array into a full set of criteria for
	 * use in a WHERE clause.
	 *
	 * If the input is a string, returns the input.
	 * If the input is an array, converts it according to the following rules:
	 *     FIELD => array(...)    FIELD IN (...)
	 *     sql => array(...)      sql IN (...)
	 *     42 => 'sql'            sql   (numeric keys will just use value)
	 *     FIELD => null          FIELD IS NULL
	 *     sql => null            sql IS NULL
	 *     FIELD => data          FIELD = data
	 *     'sql_compare' => true  sql_compare   (sql can be auto-detected)
	 *     'sql' => 42            sql = 42  (non-comparison sql uses value)
	 *
	 * Field names are passed through fieldName().
	 * Values are passed through toSqlValue().
	 * SQL is automatically detected.
	 * SQL with comparisons are automatically detected.
	 *
	 * @param mixed $where Single condition or array of conditions
	 * @return string Where clause
	 */
	public function where($where) {
		if (! is_array($where)) {
			return $where;
		}

		$out = array();

		foreach ($where as $k => $v) {
			if (is_integer($k)) {
				/* 42 => sql
				 */
				$out[] = '(' . $v . ')';
			} elseif ($this->isComparison($k)) {
				/* sql_comparison => true
				 */
				$out[] = '(' . $k . ')';
			} else {
				if ($this->isField($k)) {
					$k = $this->field($k);  // Could be Table.field, so do not use ->fieldName()
				} else {
					$k = '(' . $k  . ')';
				}

				if (is_array($v)) {
					/* FIELD => array(...)
					 * sql => array(...)
					 */
					$valueList = array();
					foreach ($v as $singleValue) {
						$valueList[] = $this->toSqlValue($singleValue);
					}
					$out[] = $k . ' IN (' . implode(', ', $valueList) . ')';
				} elseif (is_null($v)) {
					/* FIELD => null
					 * sql => null
					 */
					$out[] = $k . ' IS NULL';
				} else {
					/* FIELD => data
					 * sql => data
					 */
					$out[] = $this->toSqlValue($v, $k);
				}
			}
		}

		return implode(' AND ', $out);
	}
}
