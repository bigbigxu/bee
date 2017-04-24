<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/6/10
 * Time: 10:52
 * 文件操作类
 */

namespace bee\common;

class File
{
    private static $_instance;
    private $_spl;
    protected $fileName;
    //一次最大读取的行数，避免占过多内存。
    protected $readMaxLine = 2000;

    public function __construct($fileName, $mode = 'r')
    {
        $this->_spl = new \SplFileObject($fileName, $mode);
        $this->fileName = $fileName;
    }

    /**
     * @param $fileName
     * @param string $mode
     * @return File
     */
    public static function getInstance($fileName, $mode = 'r')
    {
        if (!is_object(self::$_instance)) {
            self::$_instance = new self($fileName, $mode);
        }
        return self::$_instance;
    }

    /**
     * @return SplFileObject
     */
    public function getSpl()
    {
        return $this->_spl;
    }

    /**
     * 根据文件行号，读取文件。读取多行
     * 说明，行号以0开始
     * @param int $start 开始读取的位置，负数表示从文件尾部开始读
     * @param int $length 读取的行数
     * @return array 二维数组
     */
    public function readByLine($start = 0, $length = 1)
    {
        $length = min($length, $this->readMaxLine);
        if ($start >= 0) {
            $this->_spl->seek($start);
        } else {
            $lastLine = $this->getMaxLine();
            $start = max(0, $lastLine - abs($start) + 1);
            $this->_spl->seek($start);
        }

        $content = array();
        for ($i = 0; $i < $length; $i++) {
            if ($this->_spl->eof()) {
                break;
            }
            $content[$start + $i] = $this->_spl->current();
            $this->_spl->next();
        }
        return $content;
    }

    /**
     * 根据行号读取一行的字符
     * @param $line
     * @return mixed
     */
    public function readLine($line)
    {
        $content = $this->readByLine($line, 1);
        return $content[$line];
    }

    /**
     * 得到当前文件最后一行的行号
     * @return int|array
     */
    public function getMaxLine()
    {
        //这里已经移动文件最后面，不能读取最后一行的内容
        $this->_spl->seek($this->_spl->getSize());
        $lastLine = $this->_spl->key();
        $this->_spl->rewind();
        return $lastLine;
    }

    /**
     * 得到目录下所有文件。
     *
     * @param string $path
     * @param array $rules 文件筛选规则
     * 支持的参数
     * allow_ext 允许的文件后缀 他与forbid_ext只能存在一个
     * match 文件名的规则，为一个正则表达式。
     * max_time 文件最后修改的最大时间
     * min_time 文件最后修改的最小时间。
     * @return array flase
     */
    static public function getAllFiles($path, $rules = array())
    {
        if (!file_exists($path)) {
            return false;
        }
        $mydir = dir($path);
        $arr = array();
        while (($file = $mydir->read()) !== false) {
            $p = $path . '/' . $file;
            if ($file == "." || $file == "..") {
                continue;
            }
            if (is_dir($p)) {
                $arr = array_merge($arr, self::getAllFiles($p, $rules));
                continue;
            }
            //判断是不是需要的扩展名。
            if ($rules['allow_ext']) {
                if (!is_array($rules['allow_ext'])) {
                    $rules['allow_ext'] = array($rules['allow_ext']);
                }
                $pathInfo = pathinfo($p);
                $extension = $pathInfo['extension'];
                if (!in_array($extension, $rules['allow_ext'])) {
                    continue;
                }
            }
            //判断文件名正则规则
            if ($rules['match'] && !preg_match($rules['match'], $p)) {
                continue;
            }
            //判断文件最后修改时间
            if ($rules['max_time'] && filemtime($p) >= strtotime($rules['max_time'])) {
                continue;
            }
            if ($rules['min_time'] && filemtime($p) <= strtotime($rules['min_time'])) {
                continue;
            }
            $arr[] = $p;
        }


        return $arr;
    }

    /**
     * 将一个php数组保存为一个文件
     * @param $fileName
     * @param $arr
     */
    public static function savePhpArr($fileName, $arr)
    {
        $str = "<?php\nreturn " . var_export($arr, true) . ';';
        file_put_contents($fileName, $str);
    }
}