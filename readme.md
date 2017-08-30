## 介绍
1. 支持数据库常用模块，包括：自动分表机制、字段缓存、读写分离等
2. 支持swoole，便于应用开发网络服务器
3. 提供常用的功能类，包括：myql、redis、sphinx、mongodb、curl等
4. 可安全运行于php-fpm模式和php-cli常驻进程模式
5. 提供类的统一加载机制，支持psr-4标准
6. 支持类配置化，事件，回调，服务定位器等

## 文档列表

1. [psr代码规范](doc/php_psr.md)
1. [开始使用](doc/创建应用.md)
2. [增删改查](doc/增删改查.md)
3. [redis操作](doc/redis操作.md)
4. [服务定位器](doc/服务定位器.md)
5. [server使用](doc/server使用.md)


## 2.0 版本升级说明
2.0 修改了部分类名，并全部加上了命名空间。
使用了类别名技术，使用老的类名，任然可以正常加载。
入口文件中，`App::getInstance()`修改为： `bee\App::getInstance()`