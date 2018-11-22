# Eagleye
server/monitor.php 是主控端，只需要里面配置上smtp发邮件的相关东西，然后在你想要做服务器的机器上执行 php eagleye/server/monitor.php 就可以了
object/ 下面是客户机的监控，目前我只放了一个示例，执行的时候 ，在你想要监控的主机上执行 php eagleye/object/app150.php 就行

服务端需要安装swoole 1.8 以上
客户端的话，装php内置扩展sockets 和 pcntl
