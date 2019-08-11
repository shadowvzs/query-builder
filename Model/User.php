<?php
namespace Model;

class User extends Core\Model {

    public $name = 'users';
    public $USER_STATUS = ['Guest', 'Active', 'Banned', 'Deleted'];
	public $hidden = ['password'];
    public $rules = [
        'id'          => ['type' => 'INTEGER'],
        'rank'        => ['type' => 'INTEGER', 'default' => 1],
        'status'      => ['type' => 'INTEGER', 'default' => 1],
        'name'        => ['type' => 'NAME_HUN', 'length' => [5, 50]],
        'email'       => ['type' => 'EMAIL', 'length' => [5, 50], 'isUnique' => false],
        'password'    => ['type' => 'STRING', 'length' => [3, 50]],
        'phone'       => ['type' => 'PHONE', 'length' => [6, 50]],
        'address'     => ['type' => 'STRING', 'length' => [3, 50]],
        'city'        => ['type' => 'STRING', 'length' => [3, 50]],
        'country'     => ['type' => 'STRING', 'length' => [3, 50]],
        'ip'          => ['type' => 'STRING', 'length' => [3, 20], 'default' => '$_IP' ],
        'browser'     => ['type' => 'STRING', 'length' => [3, 255], 'default' => '$_AGENT'],
        'reg_hash'    => ['type' => 'STRING', 'length' => [3, 255]],
        'rec_hash'    => ['type' => 'STRING', 'length' => [3, 255]],
        'last_action' => ['type' => 'INTEGER'],
        'login'       => ['type' => 'INTEGER'],
 	];
}
?>
