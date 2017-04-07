<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/7
 * Time: 10:26
 * 配置文件管理器
 */
namespace bee\common;
use bee\cache\ICache;
use bee\core\Component;

class DbConfig extends Component
{
    const EVENT_BEFORE_SAVE = 'before_save';
    /**
     * 使用的db组件
     * @var string
     */
    public $db = 'db';
    /**
     * 使用的cache组件
     * @var string
     */
    public $cache = 'cache';
    /**
     * 使用的表名
     * @var string
     */
    public $table = 'bee_config';

    /**
     * @return ICache
     */
    public function getCache()
    {
        return \App::s()->sure($this->cache);
    }

    /**
     * @return \CoreMysql
     */
    public function getDb()
    {
        return \App::s()->sure($this->db)->from($this->table);
    }

    public function buildKey($k)
    {
        return [__CLASS__, $this->table, $k];
    }

    /**
     * 插入或修改一个配置
     * @param $k
     * @param $v
     * @throws \Exception
     */
    public function save($k, $v)
    {
        $this->getDb()->save(['k' => $k, 'v' => json_encode($v)]);
        $this->getCache()->set($this->buildKey($k), $v);
    }

    /**
     * 删除一个配置
     * @param $k
     * @throws \Exception
     */
    public function del($k)
    {
        $this->getDb()->delById($k);
        $this->getCache()->del($this->buildKey($k));
    }

    /**
     * 获取一个配置
     * @param $k
     * @param bool $checkCache
     * @return array|bool|mixed
     */
    public function get($k, $checkCache = true)
    {
        $c = false;
        if ($checkCache) {
            $c = $this->getCache()->get($this->buildKey($k));
        }
        if ($c === false) {
            $dbRes = $this->getDb()->findById($k);
            $c = (array)json_decode($dbRes['v'], true);
            $this->getCache()->set($this->buildKey($k), $c);
        }
        return $c;
    }

    public static function getCreateTableSql()
    {
        return <<<SQL
create table bee_config
(
   k varchar(64) not null primary key not null comment 'key',
   v text not null comment '数据'
)ENGINE=innodb DEFAULT CHARSET=utf8 comment 'bee配置表';
SQL;
    }
}