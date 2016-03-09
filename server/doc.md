/**
 * 说明如果程序转为后台运行，文件路径请使用绝对路径
 *
 * 说明，关于服务器安全关闭
 * kill -s  SIGTERM  `ps -ef|grep server.php|awk '{print $2}'` 向主进程发送SIGTER信号
 *
 * 使用下面这个命令杀死所有相关进程
 * ps -eaf |grep "vod_server.php" | grep -v "grep"| awk '{print $2}'|xargs kill -s  SIGTERM
 *
 * 重启worker进程
 * kill -s  SIGUSR1 2862　向管理进程发送SIGUSR1信号
 *
 * 关于定时器的说明
 *     定时器最好不好加在主进程和管理进程中,如果定时器出现意外,会导致服务挂掉
 *     定时器加在worker进程中,根据worker_id为一个里程加定时器,不然所有
 *     worker进程都有定时器.
 *     worker重建时,定里器也会重建.
 *     如果任务没有完成，定时器会等待，直到任务完成，才会触发下一次定时。
 *  说明，注意如果worker进程用户不是root用户，要注意文件操作的权限问题。
 *  关于异步操作
 *  如果操作同上资源，这些资源之间，可能存在相互影响。如redis数据库选择
 *  你要操作1,可能其它进程将其选择为2了。所以他们之间不能使用共享链接
 *
 *  注意２：swoole会保存对象一定的时间。这时操作资源一定要小心。可能会导链接失效。比如redis链接超时。
 *  所有静态保存区的变量，类，对象等都会常驻对象，入口文件中创建的对象也会常驻内存。
 *  所以，类被修改后，要重启服务才能生效。
 *
 *  注意3　:task worker进程并不共享数据。所有执行任务的时候每人一份。这个时候要注意，
 *  静态资源不会在执行结后关闭（因为进程没有关闭），所有需要在执行完成后主动关闭连接，以免过多task,worker
 *  占用了过多的连接资源。连接长驻进程也可以，不过进程的数量要与服务支持连接数量相当才行。
 *
 *  注意4　各个进程之间不共享数据，资源连接，各自有各自的数据复制。所以在使用消息队列和task，worker
 *  时要注意，每个进程都有自己的内存区，比如mysql连接各个进程不共享
 *  用定时器执行消息队列的时候，最好在过时器的worker里程处理消息，不要task，如果有mysql操作，会造成大量的连接。
 *
 * 使用当前最稳定版本，1.7.4
 *
 * 内核参数调整
 * 修改 /etc/sysctl.conf，添加如下参数设置。
#当 SYN 等待队列溢出时，启用 cookie。
net.ipv4.tcp_syncookies = 1

# 进入SYN包的最大请求队列.默认1024.对重负载服务器,增加该值显然有好处
net.ipv4.tcp_max_syn_backlog=81920

# tcp_synack_retries和tcp_syn_retries定义SYN的重试次数。
net.ipv4.tcp_synack_retries=3
net.ipv4.tcp_syn_retries=3

# 表示如果套接字由本端要求关闭，这个参数决定了它保持在FIN-WAIT-2状态的时间
net.ipv4.tcp_fin_timeout = 30

# 表示当keepalive起用的时候，TCP发送keepalive消息的频度。缺省是2小时
net.ipv4.tcp_keepalive_time = 300

# 表示开启TCP连接中TIME-WAIT sockets的快速回收,默认为0,表示关闭
net.ipv4.tcp_tw_recycle = 1

# 表示开启重用,允许将TIME-WAIT sockets重新用于新的TCP连接,默认为0,表示关闭
# 此函数的作用是，Server重启时可以快速重新使用监听的端口
net.ipv4.tcp_tw_reuse= 1

# 表示用于向外连接的端口范围。缺省情况下过窄：32768到61000
net.ipv4.ip_local_port_range = 20000 65000

# 表示系统同时保持TIME_WAIT套接字的最大数量 默认为180000,可适当增大该值，但不建议减小
net.ipv4.tcp_max_tw_buckets = 200000

#
net.ipv4.route.max_size = 5242880

# 如果请求量很大，需要调整此参数,增加worker进程也可以
net.unix.max_dgram_qlen = 100

# net.core.wmem_max 修改此参数增加socket缓存区的内存大小
net.core.wmem_default = 8388608
net.core.rmem_default = 8388608
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216

# 如果使用消息队列作为IPC，请修改此参数
kernel.msgmnb = 65536
kernel.msgmax = 65536

# 开启core-dump后，一旦程序发生异常，会将进程导出到文件。对于调查程序问题有很大的帮助
kernel.core_pattern = /data/vod_server/core-%e-%p-%t

执行　sysctl -p　使用修改生效。

 * @author xuen
 *
 */