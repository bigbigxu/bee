<?php
/**
 * db_server默认配置文件
 */
return array(
    /**
     * swoole server启动配置文件
     * 也就是swoole_server::set调用时设置的参数
     */
    'serverd'=>array(
        //设置启动的worker进程数量,建议cpu核心书数的 1-4倍
        'worker_num' => 4,

        //一个worker进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出。
        'max_request' => 10240,

        //支持的tcp最大连接数
        'max_conn' => 1024,

        //配置task进程的数量，配置此参数后将会启用task功能
        'task_worker_num' => 8,


        //设置task进程的最大任务数
        'task_max_request' => 1024,

        //设置task的数据临时目录,如果投递的数据超过8192字节，将启用临时文件来保存数据
        'task_tmpdir' => '/tmp',

        //数据包分发策略。可以选择3种类型，默认为2
        'dispatch_mode' => 2,

        //是不是做为守护进程
        'daemonize' => false,

        //Listen队列长度
        'backlog' => 128,

        //启用心跳检测，此选项表示每隔多久轮循一次
        // 60秒没有向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60,

        //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
        'heartbeat_idle_time' => 60,


        'open_eof_check' => true, //打开EOF检测
        'package_eof' => "\r\n", //设置EOF
        'open_eof_split' => true,

        /**
         * 设置worker/task子进程的所属用户。服务器如果需要监听1024以下的端口，
         * 必须有root权限。但程序运行在root用户下，代码中一旦有漏洞，
         * 攻击者就可以以root的方式执行远程指令，风险很大。配置了user项之后，
         * 可以让主进程运行在root权限下，子进程运行在普通用户权限下。
         */
        'user' => 'www-data',
        'group' => 'www-data', //运行用户组
    ),

    /**
     * server启动相关的全局配置。
     */
    'server' => array(
        //主机
        'host' => '0.0.0.0',

        //端口
        'port' => 9501,

        //服务器模式，默认使用进程模式
        'server_mode' => SWOOLE_PROCESS,

        //socket模式，默认为tcp
        'socket_type' => SWOOLE_SOCK_TCP,

        //开发环境类型
        'env' => 'pro',

        //是否为debug模式
        'debug' => false,

        'server_name' => 'db_server',

        //程序运行根目录
        'base_dir' => __DIR__ . '/../runtime/db_server',

        /**
         * load_bee 表示是否加载bee框架代码
         * bee_config表示框架主配置文件的路径
         */
        'load_bee' => true,
        'bee_config' => array(), //没有配置
    ),

    /**
     * 队列的相关配置
     */
    'queue' => array(
        'redis' => 'redis.main', //此配置文件指向应用层的配置文件
        'key' => 'db_server_queue_key',
        'qps' => 100, //每个进程每秒处理的队列数量
        'tick' => 1000, //每多少秒执行一次定时器。单位毫秒。
        'queue_mode' => 1, //消息队列方式。1绑定db,tablename, 2随机
        'use_trans' => 1, //是否使用事务来批量提交
    ),
);