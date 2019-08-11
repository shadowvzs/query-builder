<?php
define('DEBUG', true);
if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// register dynamic class loading handler
spl_autoload_register(function ($class) { include str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php'; });

$DB = [
    "HOST" => '****',
    "USER" => '****',
    "PASSWORD" => '****',
    "DATABASE" => '****'
];

$User = new \Model\User($DB);
$Log = new \Model\Log($DB);

$res[] = $User->builder()->avg('id')->run();

$res[] = $User->builder()->count()->run();

$res[] = $Log->getPage(1, 2);

$res[] = $User->getById(46);

$res[] = $User->builder()
    ->select('albums.id')
    ->join('albums', 'inner', ['user_id', 'id'])
    ->limit(3)
    ->run();

$res[] = $User->builder()
        ->count()
        ->join('albums', 'inner', ['user_id', 'id'])
        ->join('images', 'inner', ['album_id', 'albums.id'])
        ->where(['images.status', 0])
        ->run();

// insert without validation
$res[] = $Log->builder()
    ->insert([
        'user_id' => 1,
        'type' => 1,
        'action' => '234234234',
        'data' => 'asdasdasdasd'
    ])
    ->run();

// insert with validation
$res[] = $Log->save([
        'user_id' => 1,
        'type' => 1,
        'action' => '234234234',
        'data' => 'asdasdasdasd'
    ]);

$res[] = $Log->builder()
    ->delete()
    ->where(['id', $res[count($res) - 1]['id']])
    ->run();

debug(...$res);

function debug(...$args) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo '<pre>';
    echo "<font color='red'><i>{$caller['file']}: {$caller['line']}</i></font><br /><font color='blue'>";
    $count = func_num_args();
    foreach($args as $nr => $var) {
        if ($nr > 0) echo "<br /><br />";
        if ($count > 1) echo "<font color='black'><b>arg$nr:</b></font> <br />";
        $str = var_export($var, true);
        $str = preg_replace("/^/m", "    ", $str);
        echo $str;
        // print_r($var);
    }
    echo '</font></pre>';
}

?>
