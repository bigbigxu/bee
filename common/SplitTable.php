<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/9/23
 * Time: 14:14
 */
class SplitTable
{
    /**
     * 返回按周创建表的sql
     * @param string $prefix 表前缀，也是原表
     * @param null|int $stamp
     * @return string
     */
    public static function weekSql($prefix, $stamp = null)
    {
        $prefix = rtrim($prefix, '_');
        if ($stamp === null) {
            $stamp = time();
        }
        $tableName = $prefix . date('_Y_W', $stamp);
        $sql = "create table if not exists {$tableName} like {$prefix}";
        return $sql;
    }

    /**
     * 返回按月创建表的sql
     * @param string $prefix 表前缀，也是原表
     * @param null|int $stamp
     * @return string
     */
    public static function monthSql($prefix, $stamp = null)
    {
        $prefix = rtrim($prefix, '_');
        if ($stamp === null) {
            $stamp = time();
        }
        $tableName = $prefix . date('_Y_m', $stamp);
        $sql = "create table if not exists {$tableName} like {$prefix}";
        return $sql;
    }

    /**
     * 返回按月创建表的sql
     * @param string $prefix 表前缀，也是原表
     * @param null|int $stamp
     * @return string
     */
    public static function daySql($prefix, $stamp = null)
    {
        $prefix = rtrim($prefix, '_');
        if ($stamp === null) {
            $stamp = time();
        }
        $tableName = $prefix . date('_Y_z', $stamp);
        $sql = "create table if not exists {$tableName} like {$prefix}";
        return $sql;
    }
}
