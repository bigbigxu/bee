<?php
$sysDir = __DIR__;
return [
    'App' => $sysDir . '/App.php',

    /* cache目录 */
    'bee\cache\ICache' => $sysDir . '/cache/ICache.php',
    'bee\cache\FileCache' => $sysDir . '/cache/FileCache.php',
    'bee\cache\RedisAnalysis' => $sysDir . '/cache/RedisAnalysis.php',
    'bee\cache\RedisSession' => $sysDir . '/cache/RedisSession.php',
    'bee\cache\MysqlCache' => $sysDir . '/cache/MysqlCache.php',
    'bee\cache\RedisCache' => $sysDir . '/cache/RedisCache.php',

    /* client 目录 */
    'bee\client\BaseClient' => $sysDir . '/client/BaseClient.php',
    'CoreSocket' => $sysDir . '/client/CoreSocket.php',
    'bee\client\LogClient' => $sysDir . '/client/LogClient.php',
    'bee\client\ProcOpen' => $sysDir . '/client/ProcOpen.php',
    'bee\client\Ssh' => $sysDir . '/client/Ssh.php',
    'Curl' => $sysDir . '/client/Curl.php',

    /* common 目录 */
    'bee\common\BeeClassMap' => $sysDir . '/common/BeeClassMap.php',
    'CoreFile' => $sysDir . '/common/CoreFile.php',
    'CoreJson' => $sysDir . '/common/CoreJson.php',
    'CoreMsg' => $sysDir . '/common/CoreMsg.php',
    'bee\common\Csv' => $sysDir . '/common/Csv.php',
    'FileUpload' => $sysDir . '/common/FileUpload.php',
    'Ftp' => $sysDir . '/common/Ftp.php',
    'Functions' => $sysDir . '/common/Functions.php',
    'Image' => $sysDir . '/common/Image.php',
    'LinuxCrontab' => $sysDir . '/common/LinuxCrontab.php',
    'Mcrypt' => $sysDir . '/common/Mcrypt.php',
    'Pack' => $sysDir . '/common/Pack.php',
    'SplitTable' => $sysDir . '/common/SplitTable.php',
    'StructXml' => $sysDir . '/common/StructXml.php',
    'Timer' => $sysDir . '/common/Timer.php',
    'Zlib' => $sysDir . '/common/Zlib.php',

    /* core目录 */
    'Call' => $sysDir . '/core/Call.php',
    'CoreController' => $sysDir . '/core/CoreController.php',
    'CoreLog' => $sysDir . '/core/CoreLog.php',
    'CoreModel' => $sysDir . '/core/CoreModel.php',
    'CoreMysql' => $sysDir . '/core/CoreMysql.php',
    'CoreRedis' => $sysDir . '/core/CoreRedis.php',
    'CoreReflection' => $sysDir . '/core/CoreReflection.php',
    'CoreSphinx' => $sysDir . '/core/CoreSphinx.php',
    'CoreValidate' => $sysDir . '/core/CoreValidate.php',
    'bee\core\Event' => $sysDir . '/core/Event.php',
    'bee\core\Module' => $sysDir . '/core/Module.php',
    'bee\core\Mongo' => $sysDir . '/core/Mongo.php',
    'Object' => $sysDir . '/core/Object.php',
    'PhpEnv' => $sysDir . '/core/PhpEnv.php',
    'PhpError' => $sysDir . '/core/PhpError.php',
    'bee\core\ServiceLocator' => $sysDir . '/core/ServiceLocator.php',
    'SwooleController' => $sysDir . '/core/SwooleController.php',
    'SphinxClient' => $sysDir . '/core/sphinxapi.php',

    /* lib　目录　*/
    'bee\lib\Hash' => $sysDir . '/lib/Hash.php',
    'bee\lib\LibEvent' => $sysDir . '/lib/LibEvent.php',
    'TokenBucket' => $sysDir . '/lib/TokenBucket.php',

    /* mutex目录*/
    'bee\mutex\IMutex' => $sysDir . '/mutex/IMutex.php',
    'bee\mutex\FileMutex' => $sysDir . '/mutex/FileMutex.php',
    'bee\mutex\RedisMutex' => $sysDir . '/mutex/RedisMutex.php',
    'bee\mutex\MysqlMutex' => $sysDir . '/mutex/MysqlMutex.php',

    /* object目录 */
    'bee\object\Arr' => $sysDir . '/object/Arr.php',
    'HttpObject' => $sysDir . '/object/HttpObject.php',
    'bee\object\Math' => $sysDir . '/object/Math.php',
    'bee\object\Str' => $sysDir . '/object/Str.php',
    'WeiXin' => $sysDir . '/object/WeiXin.php',

    /* server目录 */
    'bee\server\BaseServer' => $sysDir . '/server/BaseServer.php',
    'bee\server\Config' => $sysDir . '/server/Config.php',
    'bee\server\CrontabServer' => $sysDir . '/server/CrontabServer.php',
    'bee\server\DbServer' => $sysDir . '/server/DbServer.php',
    'bee\server\HttpServer' => $sysDir . '/server/HttpServer.php',
    'bee\server\LogServer' => $sysDir . '/server/LogServer.php',
    'bee\server\Process' => $sysDir . '/server/Process.php',
    'bee\server\RedisServer' => $sysDir . '/server/RedisServer.php',
    'bee\server\WebSocketServer' => $sysDir . '/server/WebSocketServer.php',
];