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

namespace NetsvrBusinessTest\Cases;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Framework\Logger\StdoutLogger;
use Illuminate\Container\Container;
use NetsvrBusiness\Common;
use NetsvrBusiness\ConfigProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TestHelper
{
    public static array $netsvrConfigForNetsvrSingle = [
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

    public static array $netsvrConfigForNetsvrDistributed = [
        'workerId' => 1,
        'netsvr' => [
            [
                'host' => '127.0.0.1',
                'port' => 6061,
                'serverId' => 0,
                //网关服务器必须支持自定义uniqId连接，即网关的netsvr.toml的配置项：ConnOpenCustomUniqIdKey，必须是：ConnOpenCustomUniqIdKey = "uniqId"
                'ws' => 'ws://127.0.0.1:6060/netsvr?uniqId=',
            ],
            [
                'host' => '127.0.0.1',
                'port' => 6071,
                'serverId' => 1,
                //网关服务器必须支持自定义uniqId连接，即网关的netsvr.toml的配置项：ConnOpenCustomUniqIdKey，必须是：ConnOpenCustomUniqIdKey = "uniqId"
                'ws' => 'ws://127.0.0.1:6070/netsvr?uniqId=',
            ],
        ],
        'processCmdGoroutineNum' => 25,
        'sendReceiveTimeout' => 5,
        'connectTimeout' => 5,
        'heartbeatIntervalMillisecond' => 25 * 1000,
        'taskSocketPoolMaxConnections' => 25,
        'taskSocketPoolWaitTimeoutMillisecond' => 3000,
    ];

    /**
     * @param array $netsvrConfig
     * @return Container
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function initContainer(array $netsvrConfig): Container
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
        $container->get(ConfigInterface::class)->set(StdoutLoggerInterface::class, ['log_level' => [
            LogLevel::DEBUG,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY,
        ]]);
        $container->singleton(StdoutLoggerInterface::class, function () use ($container) {
            return new StdoutLogger($container->get(ConfigInterface::class));
        });
        $container->bind(LoggerInterface::class, StdoutLoggerInterface::class);
        $container->get(ConfigInterface::class)->set('business', $netsvrConfig);
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
}