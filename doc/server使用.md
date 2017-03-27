## 基本概念
1. server 基于swoole 进行了封装，提供更为简单的使用方式，和必要的基本功能。
2. server 使用三层架构。server层，框架层，应用层。server层代码和框架代码完全独立。server层的作用相当于nginx。

## 编写一个server

### server 代码 server/TestServer.php
	<?php
	class TestServer extends \bee\server\BaseServer
	{
	    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
	    {
	        $this->send($fd, strtoupper($data));
	    }
	}

### 编写配置文件 test_server/config.php

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
	        //设置启动的worker进程数量,建议cpu核心书数的 1-4倍
	        'worker_num' => 4,
	
	        //一个worker进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出。
	        'max_request' => 10240,
	
	        //支持的tcp最大连接数
	        'max_conn' => 1024,
	
	        //配置task进程的数量，配置此参数后将会启用task功能
	        'task_worker_num' => 0,
	
	
	        //设置task进程的最大任务数
	        'task_max_request' => 5000,
	
	        //设置task的数据临时目录,如果投递的数据超过8192字节，将启用临时文件来保存数据
	        'task_tmpdir' => '/tmp',
	
	        //数据包分发策略。可以选择3种类型，默认为2
	        'dispatch_mode' => 2,
	
	        //是不是做为守护进程
	        'daemonize' => false,
	
	        //Listen队列长度
	        'backlog' => 128,
	
	        //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
	        'heartbeat_check_interval' => 60,
	
	        //表示连接最大允许空闲的时间
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
	
	        'server_name' => 'test_server',
	
	        //程序运行根目录
	        'base_dir' => __DIR__,
	
	        /**
	         * load_bee 表示是否加载bee框架代码
	         * bee_config表示框架主配置文件的路径
	         */
	        'load_bee' => false,
	        'bee_config' => array(), //没有配置
	    ),
	);

### 编写启动脚本 test_server/test_server

	#!/usr/local/php/bin/php
	<?php
	require __DIR__ . '/../system/server/BaseServer.php';
	require __DIR__ .'/../TestServer.php';
	$server = TestServer::getInstance();
	
	list($cmd, $config) = $server->getOptsByCli(__DIR__ . '/config.php');
	$server->setConfig($config);
	$server->run($cmd);

### 启动server

`test_server -s start -d yes --debug`
命令行支持如下选项：

	-s，指定当前服务动作，start启动，stop停止，restart重启，reload重载
	-c --config，指定启动的配置文件。如果未指定将加载默认配置
	-d --daemon，指定服务是否守护进程方式运行，yes or no
	-h --host， 指定服务监听IP，默认为0.0.0.0
	-p --port，指定服务监听端口，默认为9501
	--base_dir，指定server运行目录
	--debug，开启调试模式
	--help，查看命令帮助

server 启动后会参数如下默认行为：
1. 生成data 目录，保存数据。
2. 生成run目录，保存server运行状态和进程PID
3. 生成log目录，保存日志
4. 生成var 目录，保存进程数据。

## 高级
### 载入 bee 框架
默认情况下，server不会加载bee 框架的内容。在server配置文件server配置节中加载如下配置：

	/*
	 * load_bee 表示是否加载bee框架代码
	 * bee_config表示框架主配置文件的路径
	 */
	'load_bee' => true,
	'bee_config' => __DIR__ . '/../config/main.php'
框架载入是放在worker进程启动时，框架层也应用层代码修改只需要重载server。
### 进程数据保存
程序运行期间，如果发生了错误或异常导致进程关闭，会丢失数据。这是可以将数据保存在server的 `processData`中，进程结束是会将`processData`的数据保存到文件中，进程启动时会载入这些数据。

### 关于全局变量
server运行期间，常驻内存。一般情况下，应用层代码不建议使用全局变量，静态变量。
