# netsvr-business-coroutine

这是一个基于hyperf框架开发的，可以快速开发websocket全双工通信业务的包，它基于[https://github.com/buexplain/netsvr](https://github.com/buexplain/netsvr)
进行工作。

ps：如果你的项目是非协程的，串行执行php代码的，则可以使用这个包：[https://github.com/buexplain/netsvr-business-serial](https://github.com/buexplain/netsvr-business-serial)

## 使用步骤

1. 下载并启动网关服务：[https://github.com/buexplain/netsvr/releases](https://github.com/buexplain/netsvr/releases)
   ，该服务会启动：websocket服务器、worker服务器
2. 在hyperf项目里面安装本包以及protobuf包：
   > composer require buexplain/netsvr-business-coroutine
   >
   > php bin/hyperf.php vendor:publish buexplain/netsvr-business-coroutine
   >
   > composer require google/protobuf
3. 修改配置文件`config/autoload/business.php`，把里面的网关ip、port改成网关服务的worker服务器地址
4. 执行启动命令：`php bin/hyperf.php business:start`
5. 打开一个在线测试websocket的网页，连接到网关服务的websocket服务器，发送消息：`001你好`，注意这个`001`
   就是配置文件`config/autoload/business.php`
   里面的`workerId`，如果`workerId`不足三位，则需要补到三位，示例代码是：`str_pad((string)$workerId, 3, '0', STR_PAD_LEFT)`

## 本包的三种使用方式

如果你的项目需要接收网关主动发过来的消息，则你可以采用以下两种方式：

1. 直接启动，执行启动命令：`php bin/hyperf.php business:start`
   ，该命令会用swoole的进程池组件启动若干个进程，每个进程都与网关建立tcp连接，该tcp连接用于接收网关主动发过来的消息
2. 跟随http服务器启动，配置文件`listeners.php`添加配置`\NetsvrBusiness\Listener\StartMainSocketListener::class`
   用于在每个worker进程内部与网关建立tcp连接，该tcp连接用于接收网关主动发过来的消息

如果你的项目不需要接收网关主动发过来的消息，则你不需要做任何启动动作，直接使用`\NetsvrBusiness\NetBus`提供的静态方法与网关交互。

## 进程停止时候优雅断开与网关的连接

如果安装你的项目安装了`composer require hyperf/signal`包,则配置文件`signal.php`
必须添加配置`\NetsvrBusiness\Handler\StopHandler::class=>1`.
反之,不必做任何配置,本包的`\NetsvrBusiness\Listener\CloseListener::class`会自行处理.

