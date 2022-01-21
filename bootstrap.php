<?php

require_once __DIR__.'/vendor/autoload.php';

if (false !== getenv('GITHUB_ACTION')) {
    $dbAdapter = Zend_Db::factory('Mysqli', [
        'host' => '127.0.0.1',
        'username' => 'root',
        'password' => 'root',
        'dbname' => 'zf',
    ]);
} else {
    $dbAdapter = Zend_Db::factory('Mysqli', [
        'host' => 'mysql57.ab',
        'username' => 'testuser',
        'password' => 'test',
        'dbname' => 'mbw',
    ]);
}
Zend_Db_Table::setDefaultAdapter($dbAdapter);
