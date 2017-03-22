## 创建数据库配置。
创建数据库配置文件redis.php, 在main.php文件注册`redis => 'redis.php'`

	'main' => array(
        'host' => '192.168.100.122',
        'port' => 6334
    ),
## 在模型代码中重载redis()方法

    public function redis($name = 'redis.main')
    {
        return parent::redis($name);
    }


## 使用redis 的方法名和 php redis 扩展保持一致。

    $model = \app\model\BidInfo::getInstance();
	$model->redis()->get('xx');

## 高级

### 断线重连
CoreRedis会自动执行断线重连操作。常驻内存模式下无需担心redis连接断开的问题。这也是为什么需要封装redis类的原因之一。

### 定义错误处理模式
phpredis 扩展只有在redis 连接断开的时候才会抛出异常。如果你对一个集合执行了incr操作，你不会得到任务错误消息，除非显示调用getLastError。对每一个操作执行getLastError是非常麻烦的一件事。

CoreRedis支持发生错误的时候，记录错误日志或抛出异常。

	'main' => array(
        'host' => '192.168.100.122',
        'port' => 6334,
        'err_mode' => 'exception' //异常模式
    ),
如果没有设置，默认为`warning`, 发生错误时会产生一个E_USER_WARNING错误。

### 执行其他redis命令
CoreRedis并非包含全部的redis命令。如果你想执行其他命令，有如下方法：

	$redis = CoreRedis::getInstance('redis.main');
	$redis->getRedis()->get('t');
	$redis->evalCmd('set test 1');

这2中方法不支持断线重连。
