<?php
namespace Model;

class Log extends Core\Model {

    public $name = 'logs';
    public $rules = [
        'id'      => ['type' => 'INTEGER'],
        'user_id' => ['type' => 'INTEGER'],
        'type'    => ['type' => 'INTEGER', 'default' => 1], // 1 = query, 2 = query error
        'action'  => ['type' => 'STRING', 'length' => [0, 255]],
        'data'    => ['type' => 'STRING'],
        'ip'      => ['type' => 'STRING', 'length' => [3, 20], 'default' => '$_IP'],
 	];
}
?>
