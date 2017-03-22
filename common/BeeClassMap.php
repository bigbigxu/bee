<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/15
 * Time: 14:01
 * 类地图管理控制器
 * 这是一个辅助工具
 */
namespace bee\common;
class BeeClassMap
{
    const TYPE_BEE = 'bee';
    const TYPE_APP = 'app';

    /**
     * 获取文件的类名
     * @param $file
     * @return string
     */
    public function getClassName($file)
    {
        $str = file_get_contents($file);
        preg_match('/\s*(class|interface)\s+([\w]+)/', $str, $ma);
        if ($ma[2] == false) { /* 没有类名 */
            return false;
        }
        $className = $ma[2];
        preg_match("/\s*namespace\s+([\w\\\\]+)\s*;/", $str, $ma);
        if ($ma[1]) { /* 检查到命名空间 */
            $className = "{$ma[1]}\\{$className}";
        }
        return $className;
    }
    public function createBeeClassMap($type)
    {
        $app = \App::getInstance();
        $appDir = $app->getBaseDir();
        $sysDir = $app->getSysDir();
        if ($type == self::TYPE_BEE) {
            $classArr = (array)\CoreFile::getAllFiles($sysDir, array(
                'allow_ext' => 'php'
            ));
        } elseif ($type == self::TYPE_APP) {
            $classArr =  (array)\CoreFile::getAllFiles($appDir, array(
                'allow_ext' => 'php'
            ));
        } else {
            $classArr = [];
        }

        $classArr = array_unique($classArr);
        sort($classArr);
        $map = array();
        foreach($classArr as $file) {
            if (strpos($file, 'extension')) {
                continue;
            }
            if (($className = $this->getClassName($file)) == false) {
                continue; /* 没有检查到类名 */
            }
            if (strpos($file, $appDir) !== false) {
                $map[$className] = "\$appDir . '" .  $this->getBasePath($file, $appDir) . "'";
            } elseif(strpos($file, $sysDir) !== false) {
                $map[$className] = "\$sysDir . '" .  $this->getBasePath($file, $sysDir) . "'";
            } else {
                continue;
            }
        }
        $str = "<?php\n\$appDir = App::getInstance()->getBaseDir();\n";
        $str .= "\$sysDir = App::getInstance()->getSysDir();\n";
        $str .= "return [\n";
        foreach ($map as $class => $path) {
            $str .= "    '{$class}' => {$path},\n";
        }
        $str .= '];';
        echo "<pre>";
        echo $str;
        echo "</pre>";
    }

    public function getBasePath($file, $dir)
    {
        $str = str_replace($dir, '', $file);
        return $str;
    }

    public static function create($type = self::TYPE_BEE)
    {
        (new self)->createBeeClassMap($type);
    }
}