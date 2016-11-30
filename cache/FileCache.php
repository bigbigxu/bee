<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/11/23
 * Time: 14:21
 */
namespace bee\cache;
class FileCache implements Cache
{
    /**
     * key 前缀
     * @var string
     */
    public $keyPrefix = '';
    /**
     * 缓存路径
     * @var string
     */
    public $cachePath = './';
    /**
     * cache 文件后缀
     * @var string
     */
    public $cacheFileSuffix = '.cache';
    /**
     * 目录等级
     * @var int
     */
    public $dirLevel = 1;
    /**
     * 数据结构化函数
     * @var string
     */
    public $serializer = ['json_encode', 'json_decode'];
    /**
     * 创建的目录权限
     * @var int
     */
    public $dirMode = 0775;
    /**
     * 创建的文件权限
     * @var int
     */
    public $fileMode = 0775;
    /**
     * 过期文件回收概率。最大为 1000000
     * @var int
     */
    public $gcProbability = 10;

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $this->cachePath = \App::getInstance()->getRuntimeDir() . '/cache';
    }

    /**
     * 创建一个key
     * @param $key
     * @return string
     */
    public function buildKey($key)
    {
        if (is_string($key)) {
            $key = md5($key);
        } else {
            $key = md5(json_encode($key));
        }
        return $key;
    }

    /**
     * 获取文件保存路径
     * @param $key
     * @return string
     */
    public function getCacheFile($key)
    {
        $key = $this->buildKey($key);
        if ($this->dirLevel > 0) {
            $base = $this->cachePath;
            for ($i = 0; $i < $this->dirLevel; $i++) {
                if (($prefix = substr($key, $i + $i, 2)) !== false) {
                    $base .= DIRECTORY_SEPARATOR . $prefix;
                }
            }
            $file = $base . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        } else {
            $file = $this->cachePath . DIRECTORY_SEPARATOR . $this->cacheFileSuffix;
        }
        return $file;
    }

    /**
     * 设置一个key
     * @param $key
     * @param $value
     * @param int $timeout
     * @return bool
     */
    public function set($key, $value, $timeout = 0)
    {
        $this->gc();
        $cacheFile = $this->getCacheFile($key);
        $dir = dirname($cacheFile);
        if (@!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_string($value)) {
            $value = call_user_func($this->serializer[0], $value);
        }

        if (@file_put_contents($cacheFile, $value, LOCK_EX) !== false) {
            if ($this->fileMode !== null) {
                @chmod($cacheFile, $this->fileMode);
            }
            if ($timeout <= 0) {
                $timeout = 31536000; /* 1年 */
            }
            /* 设置文件修改时间，做为过期时间判断依据 */
            return @touch($cacheFile, time() + $timeout);
        } else {
            $error = error_get_last();
            \CoreLog::error("Unable to write cache file '{$cacheFile}': {$error['message']}");
            return false;
        }
    }

    /**
     * 获取一个key的值
     * @param $key
     * @return bool|mixed|string
     */
    public function get($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (@filemtime($cacheFile) > time()) {
            $fp = @fopen($cacheFile, 'r');
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                $value = @stream_get_contents($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                $value = call_user_func($this->serializer[1], $value);
                return $value;
            }
        }
        return false;
    }

    /**
     * 判断一个key是否存在
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $cacheFile = $this->getCacheFile($key);
        return @filemtime($cacheFile) > time();
    }

    /**
     * 返回一个key的过期时间
     * @param $key
     * @return int
     */
    public function ttl($key)
    {
        $cacheFile = $this->getCacheFile($key);
        return @filemtime($cacheFile) - time();
    }

    /**
     * 递归删除文件
     * @param $path
     * @param bool $expiredOnly
     */
    public function gcRecursive($path, $expiredOnly = true)
    {
        $handle = opendir($path);
        if ($handle === false) {
            \CoreLog::error("Unable to open dir {$path}");
        }
        while (($file = readdir($handle)) !== false) {
            if ($file[0] === '.') { /* 过滤 . ..*/
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) { /* 目录 */
                $this->gcRecursive($fullPath, $expiredOnly);
                if (!$expiredOnly) {
                    if (!@rmdir($fullPath)) {
                        $error = error_get_last();
                        \CoreLog::error("Unable to remove dir {$fullPath}: {$error['message']}");
                    }
                }
            } elseif (!$expiredOnly || $expiredOnly && @filemtime($fullPath) < time()) {
                if (!@unlink($fullPath)) {
                    $error = error_get_last();
                    \CoreLog::error("Unable to remove file '{$fullPath}': {$error['message']}");
                }
            }
        }
        closedir($handle);
    }

    /**
     * 删除文件
     * @param bool $force 是否强制执行
     * @param bool $expiredOnly 是否只删除过期文件
     */
    public function gc($force = false, $expiredOnly = true)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $this->gcRecursive($this->cachePath, $expiredOnly);
        }
    }
}