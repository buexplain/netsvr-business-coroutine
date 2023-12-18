<?php
/**
 * Copyright 2023 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace NetsvrBusinessTest\Cases;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Framework\Logger\StdoutLogger;
use Illuminate\Container\Container;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use NetsvrBusiness\Common;
use NetsvrBusiness\ConfigProvider;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\NetBus;
use NetsvrBusiness\Swo\Channel;
use NetsvrBusiness\Swo\Coroutine;
use NetsvrBusiness\Swo\WaitGroup;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use WebSocket\Client;

class MainSocketManagerTest extends TestCase
{
    protected static array $netsvrConfig = [
        'workerId' => 1,
        'netsvr' => [
            [
                'host' => '127.0.0.1',
                'port' => 6061,
                'serverId' => 0,
                //网关服务器必须支持自定义uniqId连接，即网关的netsvr.toml的配置项：ConnOpenCustomUniqIdKey，必须是：ConnOpenCustomUniqIdKey = "uniqId"
                'ws' => 'ws://127.0.0.1:6060/netsvr?uniqId=',
            ]
        ],
        'processCmdGoroutineNum' => 25,
        'sendReceiveTimeout' => 5,
        'connectTimeout' => 5,
        'heartbeatIntervalMillisecond' => 25 * 1000,
        'taskSocketPoolMaxConnections' => 25,
        'taskSocketPoolWaitTimeoutMillisecond' => 3000,
    ];

    /**
     * @return Container
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function initContainer(): Container
    {
        /**
         * @var $container ContainerInterface|Container
         */
        $container = new Container();
        Container::setInstance($container);
        Common::$container = $container;
        ApplicationContext::setContainer($container);
        Common::$workerProcessId = 1;
        $container->singleton(ConfigInterface::class, function () {
            return new Config([]);
        });
        $container->singleton(StdoutLoggerInterface::class, function () use ($container) {
            return new StdoutLogger($container->get(ConfigInterface::class));
        });
        $container->bind(LoggerInterface::class, StdoutLoggerInterface::class);
        $container->get(ConfigInterface::class)->set('business', static::$netsvrConfig);
        $configProvider = (new ConfigProvider())();
        foreach ($configProvider['dependencies'] as $k => $v) {
            if (method_exists($v, '__invoke')) {
                //这里要绑定成单例
                $container->singleton($k, function () use ($container, $v) {
                    $v = $container->get($v);
                    return $v();
                });
            } else {
                $container->bind($k, $v);
            }
        }
        return $container;
    }

    /**
     * composer test -- --filter=testMainSocketStart
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketStart(): void
    {
        self::initContainer();
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->start(), '连接到网关失败');
        $this->assertTrue(count(Common::$container->get(MainSocketManagerInterface::class)->getSockets()) === count(static::$netsvrConfig['netsvr']), '连接成功后，可用的socket对象与预期不符');
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->getSocket(static::$netsvrConfig['netsvr'][0]['serverId']) instanceof MainSocketInterface);
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket(1991) instanceof MainSocketInterface);
    }

    /**
     * composer test -- --filter=testMainSocketClose
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketClose(): void
    {
        self::initContainer();
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->start(), '连接到网关失败');
        Common::$container->get(MainSocketManagerInterface::class)->close();
        $this->assertEmpty(Common::$container->get(MainSocketManagerInterface::class)->getSockets(), '连接关闭失败');
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket(static::$netsvrConfig['netsvr'][0]['serverId']) instanceof MainSocketInterface);
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket(1991) instanceof MainSocketInterface);
    }

    /**
     * composer test -- --filter=testMainSocketEvent
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketEvent(): void
    {
        $channel = new Channel();
        self::initContainer()->singleton(EventInterface::class, function () use ($channel) {
            return new class($channel) implements EventInterface {
                public function __construct(protected Channel $channel)
                {

                }

                public function onOpen(ConnOpen $connOpen): void
                {
                }

                public function onMessage(Transfer $transfer): void
                {
                    //收到用户发送的信息，写入到channel
                    $this->channel->push($transfer);
                }

                public function onClose(ConnClose $connClose): void
                {
                }
            };
        });

        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->start(), '连接到网关失败');
        $wg = new WaitGroup();
        $wg->add();
        $testMessage = '测试能否接收到用户数据' . uniqid();
        Coroutine::create(function () use ($channel, $wg, $testMessage) {
            try {
                //从channel中接收用户发送的信息，并判断释放正确
                for ($i = 0; $i < count(static::$netsvrConfig['netsvr']); $i++) {
                    /**
                     * @var Transfer|false $ret
                     */
                    $ret = $channel->pop();
                    $this->assertTrue($ret instanceof Transfer, '接收到的网关转发的对象与预期不符合');
                    $this->assertTrue($ret->getData() === $testMessage, '接收到的网关转发的数据与预期不符合');
                }
            } catch (Throwable $throwable) {
                var_dump($throwable->getMessage());
            } finally {
                $wg->done();
            }
        });
        //循环每个网关，并与之构建websocket连接
        foreach (static::$netsvrConfig['netsvr'] as $config) {
            //这里采用自定义的uniqId连接到网关
            //将每个网关的serverId转成16进制
            $hex = ($config['serverId'] < 16 ? '0' . dechex($config['serverId']) : dechex($config['serverId']));
            //将网关的serverId的16进制格式拼接到随机的uniqId前面
            $uniqId = $hex . uniqid();
            //从网关获取连接所需要的token
            $token = NetBus::connOpenCustomUniqIdToken($config['serverId'])['token'];
            $client = new Client($config["ws"] . $uniqId . '&token=' . $token);
            $client->setTimeout(5);
            //往当前的websocket中写入一个信息
            $workerId = str_pad((string)(static::$netsvrConfig['workerId']), 3, '0', STR_PAD_LEFT);
            $client->text($workerId . $testMessage);
            $client->disconnect();
        }
        //等待测试结束
        $wg->wait();
    }
}