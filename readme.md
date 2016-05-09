# 介绍
1. 这不是一个完整的mvc框架。只是一个轻量级，低耦合的代码库。它的作用是为了减少代码行数。
2. mysql，redis，sphinx，mongodb的操作类以及各种常用的工具类
3. 提供了mysql模型类，redis-mysql关联模型类。
4. 提供了swoole封装类。
# 简单使用
1. 只是使用使用此类库，而不加载框架的默认行为：  
	
		require 'App.php';
	    App::getInstance(array());
  此代码将加载框架所有类，并加载bee, app2个根命名空间。
2. 使用配置文件
	
		require 'App.php';
	    App::getInstance('config/main.php');
   配置文件示例： 
		
		//php 5.2不支持__DIR__
		$baseDir = dirname(dirname(__FILE__));
		$configDir = dirname(__FILE__);
		$localConfigDir = $baseDir . '/config_local';
		$config = array(
		    'base_dir' => $baseDir,
		    'config_dir' => $configDir,
		    //环境配置,pro生产环境，test本地测试环境
		    'env' => 'pro',
		    //定时器脚本存放目录
		    'crontab_dir' => $baseDir . '/crontab' ,
		    //临时文件保存目录
		    'runtime_dir' => $baseDir . '/runtime',
		
		    //数据库相关配置文件
		    'db' => 'db.php',
		    'redis' => 'redis.php',
		    'game' => 'game.php',
		);
		
		//如果存在config_local目录，这个目录放的是测试环境配置
		//此目录不用添加到svn版本库
		if(is_dir($localConfigDir)) {
		    $testConfig = include "{$localConfigDir}/main.php";
		
		    //重设配置文件路径为config_local目录
		    $config['config_dir'] = $localConfigDir;
		    //设置为调试模式
		    $config['debug'] = 1;
		    //设置为测试环境
		    $config['env'] = 'test';
		    //基于简化使用的原则，不使用递归合并数组
		    $config = array_merge($config, $testConfig);
		}
		defined('ENV') || define('ENV' , $config['env']);
		
		return $config;

	可以使用如下方式得到一个配置： 
	
		App:c('node1.node2')

    bee支持3种方式的配置文件
  	一个数组元素 形如 k=>v形式  
    一个配置文件， 形如 k => v.php，v.php配置文件返回的类型，会在你需要的时候加载到App::$config数组中。目前支持php，ini，xml3种文件格式。需要说明的是，你不能在v.php 中再定义一个数组选项为 k2 => v2.php。也就是说，如果一个配置项指向了一个配置文件，那么它只能实定义在main.php主配置文件中。
# 代码规范和类的自动加载
1. 框架代码遵守psr编码规范。
	1. 类名使用大驼峰命名，类名和文件名一至。第一个字母大写的文件必须是一个类。
	2. 变量，函数，类成员方法，使用小驼峰命名。私有类成员以 '_'开始。
	3. 常量使用大写，单词间以'_'分隔。
	4. 如果使用了命名空间，命名空间名称必须文件路径保持一至。
2. 类的加载。框架提供了多样的类加载方式，以使得框架在各种情况下，都可以工作。
	1. 加载一个目录  

			App::getInstance()->loadPackage(array(
		    'app.model' => __DIR__ . '/../model',
		    'app.controller' => __DIR__ . '/../controller',
			));
		自动加载函数会遍历目录，找到合适的文件并引入。不过，你必须保证类名和文件名一致。
	2. 使用classMap。事实目录遍历是相当低效的，这时我们可以使用类地图。  

				App::getInstance()->loadClass(array(
			     __DIR__ . '/../model/User.php', //如果类名和文件名一致。你可以不用定义key
			     'Model_Task' =>  __DIR__. '/../model/model_task.class.php', //如果如果类名和文件名不一致， key为类名，value为文件路径
			));
	3. 在配置文件中加载 pacakge， classMap。 在main.php主配置文件，你可以启动的时候加载指定的目录和类  
		
		    'autoload' => array(
		        'app.model.chess_share' => $baseDir . '/model/chess_share',
		        'app.model.user' => $baseDir . '/model/user',
		    ),
			'class_map' => array(
 				__DIR__ . '/../model/User.php'
			),
	4. 使用命名空间。和psr-4标准不同的是，框架自可以注册根命名空间，根命名空间之个的部分，需要和文件路径保持一致。框架默认注册了：bee命名空间指向框架目录，app命名空间指向 配置文件的中base_dir。 

			App::getInstance()->loadNamespace('app', __DIR__);
	 	注册 bee => system 那么bee\server\BaseServer将对应于 system/server/BaseServer.php

		由于框架核心代码会运行于php 5.2环境，所有多数框架代码没有使用命名空间。只能运行5.3以上环境的代码，都会使用命名空间

    
# 控制器和视图
非常抱歉。框架不提供控制器和视图相关的代码。
# 模型和mysql操作