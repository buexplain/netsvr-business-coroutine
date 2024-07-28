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

use NetsvrBusiness\Contract\TaskSocketFactoryInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Socket\TaskSocket;
use NetsvrBusiness\Socket\TaskSocketPool;
use NetsvrBusiness\Swo\Coroutine;
use NetsvrBusiness\Swo\WaitGroup;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class TaskSocketPoolTest extends TestCase
{
    protected static TaskSocketPool $pool;

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function setUp(): void
    {
        $container = TestHelper::initContainer(TestHelper::$netsvrConfigForNetsvrSingle);
        $factory = new class($container) implements TaskSocketFactoryInterface {
            public function __construct(protected ContainerInterface $container)
            {
            }

            public function make(TaskSocketPoolInterface $pool): TaskSocketInterface
            {
                $socket = new TaskSocket(
                    '',
                    $this->container->get(LoggerInterface::class),
                    TestHelper::$netsvrConfigForNetsvrSingle['netsvr'][0]['workerAddr'],
                    TestHelper::$netsvrConfigForNetsvrSingle['sendReceiveTimeout'],
                    TestHelper::$netsvrConfigForNetsvrSingle['connectTimeout'],
                    $pool
                );
                $socket->connect();
                return $socket;
            }
        };
        $pool = new TaskSocketPool(
            '',
            $container->get(LoggerInterface::class),
            TestHelper::$netsvrConfigForNetsvrSingle['taskSocketPoolMaxConnections'],
            $factory,
            TestHelper::$netsvrConfigForNetsvrSingle['netsvr'][0]['workerAddr'],
            TestHelper::WORKER_HEARTBEAT_MESSAGE,
            TestHelper::$netsvrConfigForNetsvrSingle['taskSocketPoolWaitTimeoutMillisecond'],
            TestHelper::$netsvrConfigForNetsvrSingle['heartbeatIntervalMillisecond'],
        );
        self::$pool = $pool;
    }

    protected function tearDown(): void
    {
        self::$pool->close();
    }

    /**
     * composer test -- --filter=testTaskSocketPoolGet
     * @return void
     * @throws Throwable
     */
    public function testTaskSocketPoolGet()
    {
        $this->assertTrue(self::$pool->get() instanceof TaskSocketInterface);
    }

    /**
     * composer test -- --filter=testTaskSocketPoolRelease
     * @return void
     * @throws Throwable
     */
    public function testTaskSocketPoolRelease()
    {
        $socket = self::$pool->get();
        self::$pool->release($socket);
        $this->assertTrue(self::$pool->get() === $socket);
        self::$pool->release(null);
        $this->assertTrue(self::$pool->get() !== $socket);
    }

    /**
     * composer test -- --filter=testTaskSocketPoolSendReceive
     * @return void
     * @throws Throwable
     */
    public function testTaskSocketPoolSendReceive()
    {
        //测试发送与接收方法
        $socket = self::$pool->get();
        $this->assertTrue($socket->send(TestHelper::WORKER_HEARTBEAT_MESSAGE), '通过taskSocket发送心跳数据失败');
    }

    /**
     * composer test -- --filter=testTaskSocketPoolConcurrencyGet
     * @return void
     * @throws Throwable
     */
    public function testTaskSocketPoolConcurrencyGet()
    {
        $sockets = [];
        $expectFailed = 100;
        //并发榨干所有连接
        $wg = new WaitGroup();
        for ($i = 0; $i < TestHelper::$netsvrConfigForNetsvrSingle['taskSocketPoolMaxConnections'] + $expectFailed; $i++) {
            $wg->add();
            Coroutine::create(function () use ($wg, &$sockets, &$expectFailed) {
                try {
                    //因为是并发的获取比总数还多的连接，所以注定又部分协程获取失败
                    $socket = self::$pool->get();
                    $sockets[spl_object_id($socket)] = $socket;
                } catch (Throwable) {
                    $expectFailed--;
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
        //判断总的连接数是否溢出
        $this->assertCount(TestHelper::$netsvrConfigForNetsvrSingle['taskSocketPoolMaxConnections'], $sockets);
        $this->assertEquals(0, $expectFailed);
        //再尝试拿一个，此时一定会拿失败
        $errMessage = '';
        try {
            self::$pool->get();
        } catch (Throwable $throwable) {
            $errMessage = $throwable->getMessage();
        }
        $this->assertTrue(str_contains($errMessage, 'wait_timeout'));
    }
}