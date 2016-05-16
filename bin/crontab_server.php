#!/usr/local/php/bin/php
<?php
/**
 * 定时器服务启动程序
 */
require __DIR__ . '/../server/BaseServer.php';
require __DIR__ . '/../server/CrontabServer.php';
require __DIR__ . '/../common/LinuxCrontab.php';
$server = new \bee\server\CrontabServer();
list($cmd, $config) = $server->getOptsByCli();
$server->setConfig($config);
$server->run($cmd);