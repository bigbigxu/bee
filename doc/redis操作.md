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


## 使用。redis 的方法名和 php redis 扩展保持一致。

    $model = \app\model\BidInfo::getInstance();
	$model->redis()->get('xx');
