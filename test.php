<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/4/12
 * Time: 17:40
 */
require 'App.php';
App::getInstance(array('env' => 'test'));
$mongo1 = \bee\core\Mongo::getInstance(array(
    'server' => array(
        '127.0.0.1:27017'
    ),
    'db_name' => 'test'
));
$mongo2 = \bee\core\Mongo::getInstance(array(
    'server' => array(
        '127.0.0.1:2717'
    ),
    'db_name' => 'test'
));
