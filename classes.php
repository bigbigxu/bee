<?php
$sysDir = __DIR__;
return [
    'bee\App' => $sysDir . '/App.php',

    /* cache目录 */
    'bee\cache\ICache' => $sysDir . '/cache/ICache.php',
    'bee\cache\FileCache' => $sysDir . '/cache/FileCache.php',
    'bee\cache\RedisAnalysis' => $sysDir . '/cache/RedisAnalysis.php',
    'bee\cache\RedisSession' => $sysDir . '/cache/RedisSession.php',
    'bee\cache\MysqlCache' => $sysDir . '/cache/MysqlCache.php',
    'bee\cache\RedisCache' => $sysDir . '/cache/RedisCache.php',

    /* client 目录 */
    'bee\client\BaseClient' => $sysDir . '/client/BaseClient.php',
    'bee\client\CoreSocket' => $sysDir . '/client/CoreSocket.php',
    'bee\client\RemoteLog' => $sysDir . '/client/RemoteLog.php',
    'bee\client\ProcOpen' => $sysDir . '/client/ProcOpen.php',
    'bee\client\Ssh' => $sysDir . '/client/Ssh.php',
    'bee\client\Curl' => $sysDir . '/client/Curl.php',

    /* common 目录 */
    'bee\common\BeeClassMap' => $sysDir . '/common/BeeClassMap.php',
    'bee\common\File' => $sysDir . '/common/File.php',
    'bee\common\Json' => $sysDir . '/common/Json.php',
    'bee\common\Csv' => $sysDir . '/common/Csv.php',
    'bee\common\FileUpload' => $sysDir . '/common/FileUpload.php',
    'bee\common\Ftp' => $sysDir . '/common/Ftp.php',
    'bee\common\Functions' => $sysDir . '/common/Functions.php',
    'bee\common\Image' => $sysDir . '/common/Image.php',
    'bee\common\LinuxCrontab' => $sysDir . '/common/LinuxCrontab.php',
    'bee\common\Mcrypt' => $sysDir . '/common/Mcrypt.php',
    'bee\common\Pack' => $sysDir . '/common/Pack.php',
    'bee\common\SplitTable' => $sysDir . '/common/SplitTable.php',
    'bee\common\StructXml' => $sysDir . '/common/StructXml.php',
    'bee\common\Timer' => $sysDir . '/common/Timer.php',
    'bee\common\Zlib' => $sysDir . '/common/Zlib.php',
    'bee\common\BeePack' => $sysDir . '/common/BeePack.php',
    'bee\common\BeeConfig' => $sysDir . '/common/BeeConfig.php',

    /* core目录 */
    'bee\core\BeeMemcache' => $sysDir . '/core/BeeMemcache.php',
    'bee\core\Call' => $sysDir . '/core/Call.php',
    'bee\core\Controller' => $sysDir . '/core/Controller.php',
    'bee\core\Log' => $sysDir . '/core/Log.php',
    'bee\core\Model' => $sysDir . '/core/Model.php',
    'bee\core\BeeMysql' => $sysDir . '/core/BeeMysql.php',
    'bee\core\BeeRedis' => $sysDir . '/core/BeeRedis.php',
    'bee\core\BeeReflection' => $sysDir . '/core/BeeReflection.php',
    'bee\core\BeeSphinx' => $sysDir . '/core/BeeSphinx.php',
    'bee\core\BeeSqlite' => $sysDir . '/core/BeeSqlite.php',
    'bee\core\Validate' => $sysDir . '/core/Validate.php',
    'bee\core\Event' => $sysDir . '/core/Event.php',
    'bee\core\Module' => $sysDir . '/core/Module.php',
    'bee\core\BeeMongo' => $sysDir . '/core/BeeMongo.php',
    'bee\core\PhpEnv' => $sysDir . '/core/PhpEnv.php',
    'bee\core\PhpError' => $sysDir . '/core/PhpError.php',
    'bee\core\ServiceLocator' => $sysDir . '/core/ServiceLocator.php',
    'bee\core\SwooleController' => $sysDir . '/core/SwooleController.php',
    'SphinxClient' => $sysDir . '/core/sphinxapi.php',
    'bee\core\TComponent' => $sysDir . '/core/TComponent.php',

    /* lib　目录　*/
    'bee\lib\Hash' => $sysDir . '/lib/Hash.php',
    'bee\lib\LibEvent' => $sysDir . '/lib/LibEvent.php',
    'bee\lib\TokenBucket' => $sysDir . '/lib/TokenBucket.php',

    /* mutex目录*/
    'bee\mutex\IMutex' => $sysDir . '/mutex/IMutex.php',
    'bee\mutex\FileMutex' => $sysDir . '/mutex/FileMutex.php',
    'bee\mutex\RedisMutex' => $sysDir . '/mutex/RedisMutex.php',
    'bee\mutex\MysqlMutex' => $sysDir . '/mutex/MysqlMutex.php',

    /* object目录 */
    'bee\object\Arr' => $sysDir . '/object/Arr.php',
    'bee\object\HttpObject' => $sysDir . '/object/HttpObject.php',
    'bee\object\Math' => $sysDir . '/object/Math.php',
    'bee\object\Str' => $sysDir . '/object/Str.php',
    'bee\object\WeiXin' => $sysDir . '/object/WeiXin.php',

    /* server目录 */
    'bee\server\BaseServer' => $sysDir . '/server/BaseServer.php',
    'bee\server\Config' => $sysDir . '/server/Config.php',
    'bee\server\CrontabServer' => $sysDir . '/server/CrontabServer.php',
    'bee\server\DbServer' => $sysDir . '/server/DbServer.php',
    'bee\server\HttpServer' => $sysDir . '/server/HttpServer.php',
    'bee\server\LocalManager' => $sysDir . '/server/LocalManager.php',
    'bee\server\LogServer' => $sysDir . '/server/LogServer.php',
    'bee\server\Process' => $sysDir . '/server/Process.php',
    'bee\server\RedisServer' => $sysDir . '/server/RedisServer.php',
    'bee\server\WebSocketServer' => $sysDir . '/server/WebSocketServer.php',
];