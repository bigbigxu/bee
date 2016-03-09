<?php
/**
 * swoole server 默认配置文件
 */
return array(
    /**
     * swoole server启动配置文件
     * 也就是swoole_server::set调用时设置的参数
     */
    'serverd'=>array(
        //poll线程的数量，一般设置为CPU核数的1-4倍,默认会启用CPU核数相同的数量
        //不建议修改此参数，如果设定值大于cpu数据，会报core
        //'reactor_num' => 4,

        //设置启动的worker进程数量,建议cpu核心书数的 1-4倍
        'worker_num' => 4,

        //一个worker进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出。
        'max_request' => 10240,

        //支持的tcp最大连接数
        'max_conn' => 1024,

        //配置task进程的数量，配置此参数后将会启用task功能
        'task_worker_num' => 0,

        //设置task进程与worker进程之间通信的方式。参数与ipc_mode配置相同。
        //'task_ipc_mode' => '' ,

        //设置task进程的最大任务数
        'task_max_request' => 1024,

        //设置task的数据临时目录,如果投递的数据超过8192字节，将启用临时文件来保存数据
        'task_tmpdir' => '/tmp',

        //数据包分发策略。可以选择3种类型，默认为2
        'dispatch_mode' => 2,

        //是不是做为守护进程
        'daemonize' => true,

        //Listen队列长度
        'backlog' => 128,

        //指定swoole错误日志文件。在swoole运行期发生的异常信息会记录到这个文件中
        //开启守护进程模式后(daemonize => true)，标准输出将会被重定向到log_file
        'log_file' => '/data/swoole/server.log',

        //启用心跳检测，此选项表示每隔多久轮循一次
        // 60秒没有向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60,

        //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
        'heartbeat_idle_time' => 60,

        //打开EOF检测，此选项将检测客户端连接发来的数据，当数据包结尾是指定的字符串时才会投递给Worker进程。
        //否则会一直拼接数据包，直到超过缓存区或者超时才会中止。当出错时swoole底层会认为是恶意连接，丢弃数据并强制关闭连接。
        'open_eof_check' => true,
        //启用open_eof_split参数后，底层会从数据包中间查找EOF，并拆分数据包。onReceive每次仅收到一个以EOF字串结尾的数据包
        'open_eof_split' => true,
        'package_eof' => "\r\n",

        //打开包长检测特性。包长检测提供了固定包头+包体这种格式协议的解析
        //'open_length_check' => true,

        //设置最大数据包尺寸
        //'package_max_length' =>'',

        //启用CPU亲和性设置。在多核的硬件平台中，启用此特性会将swoole的reactor线程/worker进程绑定到固定的一个核上
        'open_cpu_affinity' => true,

        //开启后TCP连接发送数据时会无关闭Nagle合并算法，立即发往客户端连接。在某些场景下，如http服务器，可以提升响应速度。
        'open_tcp_nodelay' => true,

        //可以设置为一个数值，表示当一个TCP连接有数据发送时才触发accept。
        'tcp_defer_accept' => 5,

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

        //是不是开启debug模式。如果开启　不在后台运行
        'debug' => false,

        //pid文件保存位置
        'pid_file' => '/data/swoole/server.pid',

        //访问日志
        'access_log' => '/data/swoole/access.log',

        //错误访问日志
        'error_log' => '/data/swoole/error.log',

        /**
         * 重定向Worker进程的文件系统根目录。
         * 此设置可以使进程对文件系统的读写与实际的操作系统文件系统隔离。提升安全性。
         */
        'chroot' => '/tmp',

        'server_name' => 'swoole',
    ),
);