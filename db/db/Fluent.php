<?php
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
 * Provide a fluent interface to the database class
 */

class DB_Fluent {
	protected $db;
	protected $fields = array();
	protected $from = array();
	protected $where = array();
	protected $groupBy = array();
	protected $having = array();
	protected $orderBy = array();
	protected $options = array();


	/**
	 * Creates a new fluent interface to the database
	 *
	 * @param DB_Base $db
	 */
	public function __construct(DB_Base $db) {
		$this->db = $db;
	}


	/**
	 * Convert the fragment into a string.
	 *
	 * @return string
	 */
	public function __toString() {
		return __CLASS__ . '(' . $this->db->__toString() . ')';
	}


	/**
	 * Adds to one of the protected arrays
	 *
	 * @param array &$target Destination array
	 * @param string $args
	 * @return $this
	 */
	protected function addToArray($target, $args) {
		if (count($args) == 1) {
			$target[] = array_shift($args);
		} else {
			$key = array_shift($args);
			$target[$key] = array_shift($args);
		}
		return $this;
	}


	/**
	 * Add DISTINCT to the SQL
	 *
	 * @return $this
	 */
	protected function distinct() {
		$this->options['distinct'] = true;
		return $this;
	}


	/**
	 * Add a field to the select list
	 *
	 * @param string $fieldName Field name
	 * @param string $as (optional) Rename as
	 * @return $this
	 */
	public function field($fieldName, $as = null) {
		return $this->addToArray($this->fields, func_get_args());
	}


	/**
	 * Add a table to the select list
	 *
	 * @param string $tableName Table name
	 * @param mixed $join (optional) Join conditions
	 * @return $this
	 */
	public function from($tableName, $join = null) {
		return $this->addToArray($this->from, func_get_args());
	}


	/**
	 * Add a grouping condition
	 *
	 * @param string $group Name of group
	 * @return $this
	 */
	public function groupBy($group) {
		return $this->addToArray($this->groupBy, func_get_args());
	}


	/**
	 * Add a condition to the having clauses
	 *
	 * @param mixed $having Having condition or key
	 * @param mixed $value (optional) Value to match
	 * @return $this
	 */
	public function having($having, $value = null) {
		return $this->addToArray($this->having, func_get_args());
	}


	/**
	 * Limit the result set
	 *
	 * @param integer $records Number of records to return
	 * @param integer|null $offset (optional) Starting record number
	 * @return $this
	 */
	public function limit($records, $offset = null) {
		$this->options['limit'] = $records;
		if (! is_null($offset)) {
			$this->options['offset'] = $offset;
		}
		return $this;
	}


	/**
	 * Runs the query and returns only 1 result.
	 *
	 * @return array
	 */
	public function one() {
		$o = $this->options;
		$this->options['one'] = true;
		$result = $this->result();
		$this->options = $o;
		return $result;
	}


	/**
	 * Add an order
	 *
	 * @param string $order Field for ordering
	 * @param mixed $ascDesc (optional) Sort order
	 * @return $this
	 */
	public function orderBy($order, $ascDesc = null) {
		return $this->addToArray($this->orderBy, func_get_args());
	}

	
	/**
	 * Return the result set
	 *
	 * @return DB_Result|array|string
	 */
	public function result() {
		return $this->db->select($this->fields, $this->from, $this->where, $this->orderBy, $this->options, $this->groupBy, $this->having);
	}


	/**
	 * Returns the generated SQL
	 *
	 * @return string
	 */
	public function sql() {
		$o = $this->options;
		$this->options['sql'] = true;
		$result = $this->result();
		$this->options = $o;
		return $result;
	}


	/**
	 * Add a condition to the where clauses
	 *
	 * @param mixed $where Where condition or key
	 * @param mixed $value (optional) Value to match
	 * @return $this
	 */
	public function where($where, $value = null) {
		return $this->addToArray($this->where, func_get_args());
	}
}
