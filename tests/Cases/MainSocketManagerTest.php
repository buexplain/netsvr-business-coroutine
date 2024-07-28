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

use Illuminate\Container\Container;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use NetsvrBusiness\Common;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Swo\Channel;
use NetsvrBusiness\Swo\Coroutine;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use WebSocket\Client;
use function NetsvrBusiness\workerAddrConvertToHex;

class MainSocketManagerTest extends TestCase
{
    protected Container|null $container = null;

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function setUp(): void
    {
        $this->container = TestHelper::initContainer(TestHelper::$netsvrConfigForNetsvrSingle);
    }

    /**
     * 每次测试后都会调用此方法关闭所有连接
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function tearDown(): void
    {
        //关闭所有连接
        Common::$container->get(MainSocketManagerInterface::class)->close();
        Common::$container->get(TaskSocketPoolMangerInterface::class)->close();
    }

    /**
     * composer test -- --filter=testMainSocketManagerStart
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketManagerStart(): void
    {
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->start(), '连接到网关失败');
        $this->assertTrue(count(Common::$container->get(MainSocketManagerInterface::class)->getSockets()) === count(TestHelper::$netsvrConfigForNetsvrSingle['netsvr']), '连接成功后，可用的socket对象与预期不符');
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->getSocket(workerAddrConvertToHex(TestHelper::$netsvrConfigForNetsvrSingle['netsvr'][0]['workerAddr'])) instanceof MainSocketInterface);
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket('abc') instanceof MainSocketInterface);
    }

    /**
     * composer test -- --filter=testMainSocketManagerClose
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketManagerClose(): void
    {
        $this->assertTrue(Common::$container->get(MainSocketManagerInterface::class)->start(), '连接到网关失败');
        Common::$container->get(MainSocketManagerInterface::class)->close();
        $this->assertEmpty(Common::$container->get(MainSocketManagerInterface::class)->getSockets(), '连接关闭失败');
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket(workerAddrConvertToHex(TestHelper::$netsvrConfigForNetsvrSingle['netsvr'][0]['workerAddr'])) instanceof MainSocketInterface);
        $this->assertFalse(Common::$container->get(MainSocketManagerInterface::class)->getSocket('abc') instanceof MainSocketInterface);
    }

    /**
     * composer test -- --filter=testMainSocketManagerEvent
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testMainSocketManagerEvent(): void
    {
        $channel = new Channel();
        $this->container->singleton(EventInterface::class, function () use ($channel) {
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
        //测试一下心跳的发送是否会产生异常
        foreach (TestHelper::$netsvrConfigForNetsvrSingle['netsvr'] as $config) {
            for ($i = 0; $i < 1000; $i++) {
                Common::$container->get(MainSocketManagerInterface::class)->getSocket(workerAddrConvertToHex($config['workerAddr']))->send(TestHelper::WORKER_HEARTBEAT_MESSAGE);
            }
        }
        //测试往每个websocket发送数据，看看能否被event处理
        $testMessage = '测试能否接收到用户数据' . uniqid();
        Coroutine::create(function () use ($testMessage) {
            //循环每个网关，并与之构建websocket连接
            foreach (TestHelper::$netsvrConfigForNetsvrSingle['netsvr'] as $config) {
                $client = new Client($config["ws"]);
                $client->setTimeout(5);
                $client->text($testMessage);
                $client->close();
                $client->disconnect();
            }
        });
        //从channel中接收用户发送的信息，并判断是否正确
        for ($i = count(TestHelper::$netsvrConfigForNetsvrSingle['netsvr']); $i > 0; $i--) {
            /**
             * @var Transfer|false $ret
             */
            $ret = $channel->pop();
            $this->assertTrue($ret instanceof Transfer, '接收到的网关转发的对象与预期不符合');
            $this->assertTrue($ret->getData() === $testMessage, '接收到的网关转发的数据与预期不符合');
        }
    }
}