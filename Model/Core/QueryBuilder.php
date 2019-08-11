<?php
namespace Model\Core;

class QueryBuilder {
    public $_query = '';                // query (string) contain the query what we send to mysql
	protected $_base = '';              // base (string )part of the query
    protected $_data = [];              // data (assoc array) for insert
	protected $_types = '';             // types (string) for mysqli bind
	protected $_values = [];            // values (array) for the query
	protected $_method;                 // method (string: insert|update|delete|select) query type

	protected $_db = null;              // db (resource) mysql connection
	protected $_model = null;           // model (instance)
	protected $_name = null;            // name (string) table name

	protected $_where = [];             // where (array) query condition
	protected $_groups = [];            // groups (array) group by fields
	protected $_orders = [];            // orders (array) order by fields
	protected $_joins = [];             // join (array) joining
    protected $_limit = '';             // limit (string) how much record we want display
    protected $_aggregated = false;     // aggregated (boolean) min, max, count, sum, avg

	public function __construct($model) {
		$this->_name = $model->name;
		$this->_db = &$model->db;
		$this->_model = &$model;
	}

	protected function nameUpdate($fields, $tableName = null) {
		if (is_array($fields)) {
			foreach($fields as &$field) $field = $this->nameUpdate($field);
		} else {
            $name = $tableName ?? $this->_name;
			$fields = (strpos($fields, '.') === false && strpos($fields, '(') === false) ? "{$name}.{$fields}" : $fields;
		}
        return $fields;
	}

    // for lazzy people maybe would be $agg_name = strtoupper(__FUNCTION__);
    public function avg($field) {
        $field = $this->nameUpdate($field);
        return $this->select("AVG($field) as aggr");
    }

    public function min($field) {
        $field = $this->nameUpdate($field);
        return $this->select("MIN($field) as aggr");
    }

    public function max($field) {
        $field = $this->nameUpdate($field);
        return $this->select("MAX($field) as aggr");
    }

    public function sum($field) {
        $field = $this->nameUpdate($field);
        return $this->select("SUM($field) as aggr");
    }

    public function count($field = 'id') {
        $field = $this->nameUpdate($field);
        return $this->select("COUNT($field) as aggr");
    }

	public function select($fields) {
		$this->_method = 'SELECT';
		if (gettype($fields) === 'string' ) {
            if (preg_match('/(min|max|avg|count|sum)\((.*?)\) as aggr/i', $fields) === 1) $this->_aggregated = true;
            $fields = [$fields];
        }
	    $fields = implode(', ', $this->nameUpdate($fields));
        $this->_base = "SELECT {$fields} FROM {$this->_name}";
		return $this;
	}

	public function delete() {
        $this->_base = "DELETE FROM {$this->_name}";
        return $this;
    }

    protected function getTypes(array $data) {
        return array_reduce(array_values($data), function($t, $v) { return $t . (gettype($v) === 'string' ? 's' : 'd'); }, '');
    }

	public function insert($data) {
        $data['created'] =  date("Y-m-d H:i:s");
		$fields = implode(', ', array_keys($data));
		$symbols = implode(', ', str_split(str_repeat('?', count($data))));
        $types = $this->getTypes($data);
        $this->_data = $data;
		$this->_types = $types;
		$this->_values = array_values($data);
		$this->_base = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->_name, $fields, $symbols);
		$this->_method = 'INSERT';
		return $this;
	}

	public function update($data) {
        $data['updated'] =  date("Y-m-d H:i:s");
		$id_index = array_search('id', array_keys($data));
		$id = false;
        $types = $this->getTypes($data);
		if ($id_index !== false) {
			$id = $data['id'];
			unset($data['id']);
			$typeArray = str_split($types);
			array_splice($typeArray, $id_index, 1);
			$types = implode('', $typeArray);
		}
		$fields = implode(', ', array_map(function($field) {
			return "{$field} = ?";
		}, array_keys($data)));
		$symbols = implode(', ', str_split(str_repeat('?', count($data))));
		$this->_types = $types;
		$this->_values = array_values($data);
		$this->_base = "UPDATE {$this->_name} SET name = ? WHERE id = ?";
		$this->_base = sprintf('UPDATE %s SET %s', $this->_name, $fields);
		if ($id !== false) $this->where(['id', '=', $id]);
		$this->_method = 'UPDATE';
		return $this;
	}

	public function join(string $table, string $type, array $fieldPair) {
        $joins = &$this->_joins;
        $field1 = $this->nameUpdate($fieldPair[0], $table); // joined table field name
        $field2 = $this->nameUpdate($fieldPair[1]);         // connected or main table name
        $type = strtoupper($type);
		array_push($joins, "{$type} JOIN {$table} ON {$field1}={$field2}");
        return $this;
	}

	public function whereOr(...$args) {
		return $this->where($args, 'OR');
	}

	public function where($args, $before = 'AND') {
		$conditions = &$this->_where;
		if (!empty($conditions) ) {
			$lastCondition = $conditions[count($conditions) - 1];
			if (!in_array($lastCondition, ['AND', 'OR'])) { array_push($conditions, " {$before} "); }
		}
		array_push($conditions, $args);
		return $this;
	}

	public function group($columns) {
		array_push($this->_groups, ...$columns);
		return $this;
	}

	public function order($columns, $direction = 'ASC') {
		array_push($this->_orders, "{$columns} {$direction}");
		return $this;
	}

    public function limit(...$args) {
        $count = func_num_args();
        if ($count === 0 || $count > 2) return;
        $this->_limit = implode(', ', $args);
        return $this;
    }

	public function build() {
		$conditions = '';
		$groups = implode(', ', $this->_groups);
		$oders = implode(', ', $this->_orders);
		$joins = implode(' ', $this->_joins);
		$types = &$this->_types;
		$values = &$this->_values;
        $limit = &$this->_limit;
		$finalQuery = &$this->_query;

		foreach ($this->_where as $value) {
			if (gettype($value) === 'string') {
				$conditions .= $value;
			} elseif (gettype($value) === 'array') {
				if (count($value) === 2) {
					$conditions .= "{$value[0]} = ?";
					$valueIndex = 1;
				} else {
					$conditions .= "{$value[0]} {$value[1]} ?";
					$valueIndex = 2;
				}
				$types .= gettype($value[$valueIndex]) === 'string' ? 's' : 'd';
				array_push($values, $value[$valueIndex]);
			}
		}

		$finalQuery = $this->_base;
		if (!empty($joins)) $finalQuery .= " {$joins}";
		if (!empty($conditions)) $finalQuery .= " WHERE {$conditions}";
		if (!empty($groups)) $finalQuery .= " GROUP BY {$groups}";
        if (!empty($oders)) $finalQuery .= " ORDER BY {$oders}";
        if (!empty($limit)) $finalQuery .= " LIMIT {$limit}";

		$finalQuery .= ';';
		return $finalQuery;
	}

	public function run() {
		$query = &$this->_query;
		if (empty($query)) $this->build();
        if (empty($query)) throw new \Exception("Query is empty");
		$con = &$this->_db;
		$stmt = $con->prepare($query);
		if (!$stmt) throw new \Exception("{$con->errno}: {$con->error}");
		if (count($this->_values) > 0) $stmt->bind_param($this->_types, ...$this->_values);
		$stmt->execute();
        if (!$stmt || !empty($con->error)) throw new \Exception("{$con->errno}: {$con->error}");
        debug($this->_query);
        if ($this->_method === "SELECT" || stripos($query, 'SELECT') === 0) {
			$rows = [];
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) $rows[] = $row;
			$hidden = $this->_model->hidden ?? [];
			if (count($hidden) > 0) {
				foreach ($rows as &$row) {
					foreach ($hidden as $field) unset($row[$field]);
				}
			}
            if ($this->_aggregated) {
                return $rows[0]['aggr'];
            }
			return $rows;
		} else if ($this->_method === "INSERT") {
            // we return the original data extended with id
            $data = $this->_data;
            $data['id'] = $con->insert_id;
            return $data;
        } else {
            // for update and delete we return true or false, depend if was successfully or not
			return $con->affected_rows > 0;
		}
	}
}
?>
