<?php
namespace Model\Core;

class Model {
	public $db = null;
	private $query = [];
	protected static $LOG = true;
	protected static $LOG_DATA = [];
	protected static $DATABASE = [
		"HOST" => 'localhost',	//getenv('IP'),
		"USER" => 'shadowvzs',		//getenv('C9_USER'),
		"PASSWORD" => '123456',	//'root',
		"DATABASE" => 'gyozelem'
	];

	protected static $PATTERN = [
		'EMAIL' => '/^([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$)$/',
		'NAME_HUN' => '/^([a-zA-Z0-9 ÁÉÍÓÖŐÚÜŰÔ??áéíóöőúüűô??]+)$/',
		'ADDRESS_HUN' => '/^([a-zA-Z0-9 ÁÉÍÓÖŐÚÜŰÔ??áéíóöőúüűô??\,\.\-]+)$/',
		'NAME' => '/^([a-zA-Z0-9 \-]+)$/',
		'INTEGER' => '/^([0-9]+)$/',
		'SLUG' => '/^[a-zA-Z0-9-_]+$/',
		'URL' => '/^[a-zA-Z0-9-_]+$/',
		'ALPHA_NUM' => '/^([a-zA-Z0-9]+)$/',
		'STR_AND_NUM' => '/^([0-9]+[a-zA-Z]+|[a-zA-Z]+[0-9]+|[a-zA-Z]+[0-9]+[a-zA-Z]+)$/',
		'LOWER_UPPER_NUM' => '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/',
		'MYSQL_DATE' => '/^([0-9]{2,4})-([0-1][0-9])-([0-3][0-9])(?:( [0-2][0-9]):([0-5][0-9]):([0-5][0-9]))?$/',
		'STRING' => ['safeString']
	];

	public function __construct($DB) {
		$this->db = self::getCon($DB);
	}

	public function __destruct() {
		// php close the connection automatically
		// $this->db->close();
	}

	protected function deleteFile($path) {
		if (file_exists($path)) {
			unlink($path);
		}
	}

	// validate the data which we will save into database
	protected function validateData($data) {
		$fieldsRules = $this->rules;
		foreach ($data as $field => $value) {
			if (empty($fieldsRules[$field])) throw new \Exception("{$field} not exist in {$this->name} table");
			$rules = $fieldsRules[$field];
			if (empty($rules)) { continue; }
			$value = trim($value);

			foreach ($rules as $rule => $cond) {
				if (!is_string($rule)) { $rule = $cond; }
				if ($rule === 'default') { continue; }
				if ($rule === "type") {
					if (!$this->validateValue($value, $cond)) {
						return "Invalid form data - $field is not $cond";
					}
				} else if ($rule === "length") {
					$len = strlen($value);
					if ($len < $cond[0] || $len > $cond[1]) {
						return "Invalid form data ".$field;
					}
				} else if ($rule === "isUnique" && $cond) {
					if ($this->count("{$field} = '{$value}'")) {
						return ucfirst($field)." already exist!";
					}
				}
			}
		}
		return true;
	}

	// use the correspoding regex validation
	protected function validateValue($str, $type = "ALPHA_NUM") {
		if (is_array(static::$PATTERN[$type])) {
			// if pattern not string then we call function,
			// example: "STRING" will be converted to static::safeString($string)
			if (static::$PATTERN[$type][0]) {
				$method = static::$PATTERN[$type][0];
				return static::$method($str);
			}
		}
		return preg_match(static::$PATTERN[$type], htmlspecialchars(trim($str), ENT_QUOTES));
	}

	// get custom data and return it
	private function getCustomDefaultValue($key, $data) {
		if ($key === 'IP') {
			return $_SERVER['REMOTE_ADDR'];
		} else if (substr($key,0, 5) === 'SLUG_') {
			return $this->slugify($data[substr($key,5)]);
		} else if ($key === 'AGENT') {
			return $_SERVER['HTTP_USER_AGENT'];
		}
		return $key;
	}

	// add default values which is described in model files like: User, Album etc
	public function addDefaultValues(&$data) {
		if (empty($this->rules)) return;
		$existingKeys = array_keys($data);
		$defaultValues = array_filter($this->rules, function($e) { return isset($e['default']); });
		foreach($defaultValues as $field => $value) {
			$default = $value['default'];
			if (gettype($default) === 'string' && substr($default, 0, 2) === '$_') {
				$data[$field] = $this->getCustomDefaultValue(substr($default, 2), $data);
			} else {
				$data[$field] = $default;
			}
		}
	}

	// create slug from string
	public function slugify ($string) {
	    $string = utf8_encode($string);
	    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
	    $string = preg_replace('/[^a-z0-9- ]/i', '', $string);
	    $string = str_replace(array(' - ',' -','- ',' '), '-', $string);
	    $string = trim($string, '-');
	    $string = strtolower($string);
	    if (empty($string)) {
	        return 'n-a';
	    }
	    return $string;
	}

	// query builder
	public function builder() {
		return new QueryBuilder($this);
	}

	// pdate or insert new record based on provided data
	public function save(array $data) {
		$method = isset($data['id']) ? 'update' : 'insert';
		$validation = $this->validateData($data);
		if ($validation !== true) { throw new \Exception($validation); }
		if ($method === 'insert') $this->addDefaultValues($data);
		$query = $this->builder();
		return $query->$method($data)->run();
	}

	public function getById(int $id) {
		return $this->getBy('id', $id);
	}

	public function getBySlug(string $slug) {
		return $this->getBy('slug', $slug);
	}

	public function getBy($column, $value) {
		return $this->builder()->select('*')->where([$column, $value])->run();
	}

	public function getPage($pageIndex, $amount) {
		return $this->builder()->select('*')->limit($pageIndex * $amount, $amount)->run();
	}

	public function count($cond = '1') {
		$result = $this->builder()->select('count(*) as c')->where($cond)->run();
		return empty($result) ? false : $result[0]['c'];
  	}

	public static function getCon($DB) {
		static $db = null;
		if (empty($db)) {
			$db = new \mysqli(
				$DB['HOST'],
				$DB['USER'],
				$DB['PASSWORD'],
				$DB['DATABASE']
			);

			$db->report_mode = MYSQLI_REPORT_ALL;

			if ($db->connect_error) {
				die($db->connect_error);
				// \Controller\App::error("Connection failed: ".$this->con->connect_error);
			}
			$db->set_charset("utf8");
		}
		return $db;
	}

	public function closeCon($con) {
		if (count(static::$LOG_DATA) > 0) { static::saveLog(); }
		$this->db->close();
	}

	protected static function viewCounter() {
		$file = '../counter.txt';
		$fdata = file_get_contents ( $file ) ?? 0;
		$fdata = intval($fdata) + 1;
		file_put_contents($file, $fdata);
	}

	protected static function safeString($data) {
		// Fix &entity\n;
		$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
		$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
		$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
		$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

		// Remove any attribute starting with "on" or xmlns
		$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

		// Remove javascript: and vbscript: protocols
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

		// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

		// Remove namespaced elements (we do not need them)
		$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

		do {
			// Remove really unwanted tags
			$old_data = $data;
			$data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
		} while ($old_data !== $data);

		// we are done...
		return $data;
	}
}
?>
