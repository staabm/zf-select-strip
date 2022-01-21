<?php

require_once __DIR__.'/vendor/autoload.php';

$dbAdapter = Zend_Db::factory('Mysqli', array(
    'host'     => 'mysql57.ab',
    'username' => 'testuser',
    'password' => 'test',
    'dbname'   => 'mbw'
));

Zend_Db_Table::setDefaultAdapter($dbAdapter);