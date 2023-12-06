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

namespace NetsvrBusiness;

use NetsvrBusiness\Command\StartWorkerCommand;
use NetsvrBusiness\Command\StatusWorkerCommand;
use NetsvrBusiness\Command\StopWorkerCommand;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\ServerIdConvertInterface;
use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Contract\TaskSocketFactoryInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Listener\CloseListener;
use NetsvrBusiness\Listener\InitContainerListener;
use NetsvrBusiness\Socket\MainSocket;
use NetsvrBusiness\Socket\Socket;
use NetsvrBusiness\Socket\TaskSocket;
use NetsvrBusiness\Socket\TaskSocketPool;

/**
 *
 */
class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke(): array
    {
        defined('BASE_PATH') or define('BASE_PATH', dirname(__DIR__, 4));
        return [
            'listeners' => [
                InitContainerListener::class,
                CloseListener::class,
            ],
            'dependencies' => [
                //最底层的socket实现
                SocketInterface::class => Socket::class,
                //mainSocket相关的接口实现
                MainSocketInterface::class => MainSocket::class,
                MainSocketManagerInterface::class => MainSocketManagerFactory::class,
                //taskSocket相关接口的实现
                TaskSocketInterface::class => TaskSocket::class,
                TaskSocketFactoryInterface::class => TaskSocketFactory::class,
                TaskSocketPoolInterface::class => TaskSocketPool::class,
                TaskSocketPoolMangerInterface::class => TaskSocketPoolMangerFactory::class,
                //处理netsvr网关发来的连接事件的实现，业务层必须实现该接口
                EventInterface::class => Event::class,
                //网关下发给连接的唯一id，转换成网关唯一id的实现，用于定位连接目前处于哪台网关机器
                ServerIdConvertInterface::class => ServerIdConvert::class,
            ],
            'commands' => [
                StartWorkerCommand::class,
                StopWorkerCommand::class,
                StatusWorkerCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of netsvr-business.',
                    'source' => __DIR__ . '/../publish/business.php',
                    'destination' => BASE_PATH . '/config/autoload/business.php',
                ],
            ],
        ];
    }
}
