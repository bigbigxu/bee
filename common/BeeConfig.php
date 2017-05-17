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
use bee\core\BeeMysql;
use bee\core\TComponent;

class BeeConfig
{
    use TComponent;
    /**
     * 使用的db组件
     * @var BeeMysql
     */
    protected $db = 'db';
    /**
     * 使用的cache组件
     * @var ICache
     */
    protected $cache = 'cache';
    /**
     * 使用的表名
     * @var string
     */
    protected $table = 'bee_config';

    public function init()
    {
        $this->sureComponent($this->db);
        $this->sureComponent($this->cache);
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
    public function set($k, $v)
    {
        $data = [
            'k' => $k,
            'v' => json_encode($v)
        ];
        $this->db->from($this->table)->upsert($data, ['v']);
        $this->cache->set($this->buildKey($k), $v);
    }
    /**
     * 删除一个配置
     * @param $k
     * @throws \Exception
     */
    public function del($k)
    {
        $this->db->from($this->table)->delById($k);
        $this->cache->del($this->buildKey($k));
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
            $c = $this->cache->get($this->buildKey($k));
        }
        if ($c === false) {
            $dbRes = $this->db->from($this->table)->findById($k);
            $c = json_decode((string)$dbRes['v'], true);
            $this->cache->set($this->buildKey($k), $c);
        }
        return $c;
    }

    public static function getCreateTableSql()
    {
        return <<<SQL
create table bee_config
(
   k varchar(64) not null primary key not null comment 'key',
   v text not null comment '数据',
   name varchar(64) not null comment '配置名称',
   show tinyint not null comment '是否显示',
   tpl varchar(64) not null comment '加载的视图文件'
)ENGINE=innodb DEFAULT CHARSET=utf8 comment '配置定义表';
SQL;
    }

    /**
     * 获取全部的配置定义
     * @return array|bool
     */
    public function getAllKeys()
    {
        $res = $this->db
            ->from($this->table)
            ->field('k, v, name, show, tpl')
            ->all();
        return $res;
    }
}