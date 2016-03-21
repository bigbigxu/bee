<?php

/**
 * 文件上传相关
 * Class FileUpload
 */
class FileUpload
{
    /**
     * 可以上传的文件类型
     * @var array
     */
    protected $allowType = array('gif', 'jpg', 'png', 'jpeg', 'txt');
    /**
     * 可否上传空文件
     * @var bool
     */
    protected $allowEmpty = false;
    /**
     * 文件最大大小
     * @var int
     */
    private $maxSize = 2000000;//2m
    const ERR_OK = 0;
    const ERR_INI_SIZE = 1;
    const ERR_FORM_SIZE = 2;
    const ERR_PARTIAL = 3;
    const ERR_NO_FILE = 4;
    const ERR_NO_TMP_DIR = 5;
    const ERR_CANT_WRITE = 7;
    const ERR_EXT = 100;
    const ERR_SIZE = 101;

    private static $_files;
    public $errArr = array(
        self::ERR_OK => '没有错误!',
        self::ERR_INI_SIZE => '文件大小超过php.ini配置',
        self::ERR_FORM_SIZE => '文件大小超过表单配置',
        self::ERR_PARTIAL => '文件只有部分被上传',
        self::ERR_NO_FILE => '没有文件被上传',
        self::ERR_NO_TMP_DIR => '打不到临时文件夹',
        self::ERR_CANT_WRITE => '文件写入失败',
        self::ERR_EXT => '不允许的文件格式',
        self::ERR_SIZE => '文件过大'
    );

    private static $_instance;
    /**
     * 错误码
     * @var int
     */
    protected $errno = self::ERR_OK;

    /**
     * 添加允许的文件类型
     * @param $type
     * @return $this
     */
    public function addAllowType($type)
    {
        array_push($this->allowType, $type);
        return $this;
    }

    /**
     * 设置文件最大上传大小
     * @param $size
     * @return $this
     */
    public function setMaxSize($size)
    {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * 设置是否允许上传空文件
     * @param $bool
     * @return $this
     */
    public function allowEmpty($bool)
    {
        $this->allowEmpty = $bool;
        return $this;
    }

    public function __construct()
    {
        self::$_files = self::loadFiles();
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!is_object(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * 上传一个文件
     * @param string $key 文件的key
     * @param string $file 文件保存路径
     * @param bool $deleteTempFile 是否删除临时文件
     * @return bool
     */
    public function save($key, $file, $deleteTempFile = true)
    {
        if (self::$_files[$key] == false) {
            return $this->setErrno(self::ERR_NO_FILE);
        }
        $info = self::$_files[$key];
        if ($info['error'] != self::ERR_OK) {
            return $this->setErrno($info['error']);
        }

        //判断文件扩展名
        $ext = $this->getExtension($info['name']);
        if (in_array($ext, $this->allowType)) {
            return $this->setErrno(self::ERR_EXT);
        }

        //判断文件大小
        if ($info['size'] > $this->maxSize) {
            return $this->setErrno(self::ERR_SIZE);
        }
        if ($deleteTempFile) {
            return move_uploaded_file($info['tempName'], $file);
        } elseif (is_uploaded_file($info['tempName'])) {
            return copy($info['tempName'], $file);
        }
        return false;
    }

    public function saveAll($config, $allowEmpty = false)
    {

    }

    /**
     * 批量上传。
     * @param array $config 一个数组，key为file下标。value,要保存的路径
     */
    public static function simpleSave($config)
    {
        $o = new self();
        foreach ($config as $key => $fileName) {
            $o->save($key, $fileName);
        }
    }

    public function setErrno($errno)
    {
        $this->errno = $errno;
        return false;
    }

    /**
     * 将$_FILES 转变成正确的形式。为一个二维数组
     * key与form中的name保持一致
     * @example
     * <pre>
     * <input type="file" name="a[1]">
     * 转变为
     * Array(
     *      [a[1]] => Array
     *      (
     *           [name] => af.txt
     *           [tempName] => /tmp/phpngL33v
     *           [type] => text/plain
     *           [size] => 10348
     *           [error] => 0
     *      )
     * )
     * </pre>
     * @return array
     */
    public static function loadFiles()
    {
        if (self::$_files !== null) {
            return self::$_files;
        }
        self::$_files = array();
        if (isset($_FILES) && is_array($_FILES)) {
            foreach ($_FILES as $class => $info) {
                self::loadFilesRecursive(
                    $class,
                    $info['name'],
                    $info['tmp_name'],
                    $info['type'],
                    $info['size'],
                    $info['error']
                );
            }
        }
        return self::$_files;
    }

    /**
     * 递归创建$_files
     * @param string $key $_FILES数组索引
     * @param mixed $names 文件名
     * @param mixed $tempNames 临时文件名
     * @param mixed $types 类型
     * @param mixed $sizes 大小
     * @param mixed $errors 错误
     */
    private static function loadFilesRecursive($key, $names, $tempNames, $types, $sizes, $errors)
    {
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                self::loadFilesRecursive(
                    $key . '[' . $i . ']', $name,
                    $tempNames[$i],
                    $types[$i],
                    $sizes[$i],
                    $errors[$i]
                );
            }
        } else {
            self::$_files[$key] = array(
                'name' => $names,
                'tempName' => $tempNames,
                'type' => $types,
                'size' => $sizes,
                'error' => $errors,
            );
        }
    }


    /**
     * @param $name
     * @return string 得到源文件名称
     */
    public function getBaseName($name)
    {
        return pathinfo($name, PATHINFO_FILENAME);
    }

    /**
     * @param $name
     * @return string 扩展名
     */
    public function getExtension($name)
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    /**
     * 创建随机文件名称
     * @param string $name 源文件名称
     * @param string $basePath 路径
     * @return string
     */
    public function createFileName1($name, $basePath = '')
    {
        $ext = $this->getExtension($name);
        $fileName = md5(uniqid('iphp_file_upload')) . '.' . $ext;
        $dir = rtrim($basePath, '/') . '/' . date('Y/m/d');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . $fileName;
    }

    /**
     * 得到最后一次的错误
     * @return mixed
     */
    public function getLastError()
    {
        return $this->errArr[$this->errno];
    }

    /**
     * 重置
     */
    public function reset()
    {
        self::$_files = null;
        $this->errno = self::ERR_OK;
    }
}