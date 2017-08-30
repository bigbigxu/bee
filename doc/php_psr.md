## PSR-0(Autoloading Standard) 类自动加载规范（已过时,请查阅PSR-4）
从2014-10-21日起，该规范被标记为Deprecated，由PSR-4替代。它的内容十分简洁。

* 1.一个完全合格的namespace和class必须符合这样的结构：“\< Vendor Name>(< Namespace>)*< Class Name>”
   
    如：文件 /Doctrine/Common/IsolatedClassLoader.php,那么namespace命名就必须声明成这样
```php
声明：namespace  \Doctrine\Common\
调用：\Doctrine\Common\IsolatedClassLoader
```
其中，Doctrine 表示一个模块目录 Vendor name, common就是namesapce, IsolatedClassLoader是class name。这样一看就知道这个文件的目录层次，一目了然。
    
    再比如：/path/to/project/lib/vendor/Symfony/Core/Request.php 文件：
```php
声明：namespace \Symfony\Core
调用：\Symfony\Core\Request
```

* 2.每个namespace必须有一个顶层的namespace（"Vendor Name"提供者名字）
* 3.每个namespace可以有多个子namespace
 
 2,3例子
```php
namespace \Zend\Acl => /path/to/project/lib/vendor/Zend/Acl.php
namespace \Zend\Mail\Message => /path/to/project/lib/vendor/Zend/Mail/Message.php
```
必须有一个顶级的zend的namespace, zend 下面可以有message子命名空间。

* 4.当从文件系统中加载时，每个namespace的分隔符(/)要转换成 DIRECTORY_SEPARATOR(操作系统路径分隔符)
 
```php
new \Symfony\Core\Request
```
在加载这个类文件时候，就要将分隔符 \ 转换成 目录，也就是去 Vendor -> Symfony->Core->Request.php 一层层的目录找到这个文件。其实也就是和第1,2,3是反过来的对应关系。

* 5.在类名中，每个下划线(_)符号要转换成DIRECTORY_SEPARATOR(操作系统路径分隔符)。在namespace中，下划线(_)符号是没有（特殊）意义的。
 
    namespace命名中的这个 _ 符号 没有任何用处，就是用来表示目录分隔符的，但是注意在PRS-4中已经取消了这个_ ，那么我们还是看一下，这个过时的规定是怎么样的：
```php
\namespace\package\Class_Name => /path/to/project/lib/vendor/namespace/package/Class/Name.php
\namespace\package_name\Class_Name => /path/to/project/lib/vendor/namespace/package_name/Class/Name.php
```
以上2个namespace中的 _ 其实是目录分隔符。并不是class name就是那样的。

* 6.当从文件系统中载入时，合格的namespace和class一定是以 .php 结尾的
* 7.verdor name,namespaces,class名可以由大小写字母组合而成（大小写敏感的）
    
    Linux系统下是区分文件名和目录名大小写的，而在Windows下是不区分的。所以就会经常出现问题
```php
namespace  \Doctrine\Common\IsolatedClassLoader
```
在Linux下就去严格按照大小写去找目录和文件了。但是如果在Windows下开发，全是小写也不会报错，一发布到Linux上就悲剧了，提示找不到文件。


## PSR-1(Basic Coding Standard) 基础编码标准 
包含了类文件、类名、类方法名的命名方法。

* 1.PHP源文件必须只使用 <?php 和 <?= 这两种标签。
* 2.源文件中php代码的编码格式必须是不带字节顺序标记(BOM)的UTF-8。
* 3.一个源文件建议只用来做声明（类(class)，函数(function)，常量(constant)等）或者只用来做一些引起副作用的操作（例如：输出信息，修改.ini配置等），但不建议同时做这两件事。
    
    别把一些输出和修改的操作(副作用) 和 类文件混合在一起，专注一点，这个文件专门来声明Class, 那个文件专门来修改配置文件，别混在一起写：
所以，以下的这个文件是有问题的，最好不要这样：

```php
// 副作用：修改了ini配置
ini_set('error_reporting', E_ALL);
 
// 副作用：载入了文件
include "file.php";
 
// 副作用：产生了输出
echo "<html>\n";
 
// 声明 function
function foo()
{
    // 函数体
}
```

最好全部分开来写:

```php
namespace Lib;
class Name
{
    public function __construct()
    {
        echo __NAMESPACE__ . "<br>";
    }
 
    public static function test()
    {
        echo __NAMESPACE__ . ' static function test <br>';
    }
}
```
修改ini:
```php
ini_set('error_reporting', E_ALL);
```
require 文件：
```php
require DIR . '/loading.php';
spl_autoload_register("\\AutoLoading\\loading::autoload");
```

* 4.命名空间(namespace)和类(class) 必须遵守PSR-0标准。 (驼峰方式)
    
* 5.类名(class name) 必须使用骆驼式(StudlyCaps)写法 (注：驼峰式(cameCase)的一种变种，后文将直接用StudlyCaps表示)。

    class name必须要用驼峰方式写，驼峰又分小驼峰和大驼峰 (小驼峰是首字母为小写)
```php
class getUserInfo
{
}
```

* 6.类(class)中的常量必须只由大写字母和下划线(_)组成。
    
    类中的常量名(const)声明必须要全部大写，如果有多个单词，就用_分开：
```php
class getUserInfo
{
    // 全部大写
    const NAME = 'phper';
 
    // 用_隔开
    const HOUSE_INFO = '已在老家买房';
 
    public function getUserName()
    {
        //
    }
}​
```

* 7.方法名(method name) 必须使用驼峰式(cameCase)写法。
method name必须要用驼峰方式写，大小驼峰都可以
```php
class getUserInfo
{
    public function getUserName()
    {
        //
    }
}
```

## PSR-2(Coding Style Guide) 编码风格标准
包含了缩进、每行代码长度、换行、方法可见性声明、空格和方法体大括号换行的相关规定。

* 1.代码必须遵守 PSR-1
* 2.代码必须使用4个空格来进行缩进，而不是用制表符
* 3.一行代码的长度不应有硬限制；软限制必须为120个字符，建议每行代码80个字符或者更少
* 4.在命名空间的声明下面必须有一行空行，并且在use的声明下面也必须有一行空行
* 5.类的左花括号必须放到其声明下面自成一行，右花括号则必须放到类主体下面自成一行
* 6.方法的左花括号必须放到其声明下面自成一行，右花括号则必须放到方法主体的下一行
* 7.所有的属性和方法必须有可见性声明；abstract和final声明必须在可见性声明之前；而static声明必须在可见性声明之后
* 8.在结构控制关键字的后面必须有一个空格；而方法和函数调用时后面不可有空格
* 9.结构控制的左花括号必须跟其放在同一行，右花括号必须放在该结构控制代码主体的下一行
* 10.控制结构的左括号之后不可有空格，右括号之前也不可有空格

## PSR-3(Logger Interface) 对应用日志类的通过接口的定义
    就是一个接口，官方示例代码引用一下就好了。当然，在具体的应用中，只要遵循该接口，就可定制相应的实现。
* 1.LoggerInterface暴露八个接口用来记录八个等级(debug, info, notice, warning, error, critical, alert, emergency)的日志。
* 2.第九个方法是log，接受日志等级作为第一个参数。用一个日志等级常量来调用这个方法必须和直接调用指定等级方法的结果一致。用一个本规范中未定义且不为具 体实现所知的日志等级来调用该方法必须抛出一个Psr\Log\InvalidArgumentException。不推荐使用自定义的日志等级，除非你非常确定当前类库对其有所支持。

## PSR-4(Improved Autoloading) 改进版的自动加载规范,PSR-0规范的接替者,可以与任何其它的自动加载规范兼容，包括PSR-0。

* 1.术语「类」是一个泛称；它包含类，接口，traits以及其他类似的结构
* 2.完全限定类名应该类似如下范例：
    *  1.完全合规类名必须有一个顶级命名空间（Vendor Name）
    *  2.完全合规类名可以有多个子命名空间
	*  3.完全合规类名应该有一个终止类名
	*  4.下划线在完全合规类名中是没有特殊含义的
	*  5.字母在完全合规类名中可以是任何大小写的组合
	*  6.所有类名必须以大小写敏感的方式引用
* 3.当从完全合规类名载入文件时：
	1.在完全合规类名中，连续的一个或几个子命名空间构成的命名空间前缀（不包括顶级命名空间的分隔符），至少对应着至少一个基础目录
	2.在「命名空间前缀」后的连续子命名空间名称对应一个「基础目录」下的子目录，其中的命名空间分隔符表示目录分隔符。子目录名称必须和子命名空间名大小写匹配
	3.终止类名对应一个以.php结尾的文件。文件名必须和终止类名大小写匹配
* 4.自动载入器的实现不可抛出任何异常，不可引发任何等级的错误；也不应返回值