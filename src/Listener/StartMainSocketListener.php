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

namespace NetsvrBusiness\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use NetsvrBusiness\Common;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 在swoole的异步风格服务器的worker进程内，打开与网关的tcp连接
 */
class StartMainSocketListener implements ListenerInterface
{
    /**
     * @param ContainerInterface $container
     */
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            //swoole异步风格服务器会触发该事件
            AfterWorkerStart::class,
            //swoole协程风格服务器，swow引擎时会触发该事件
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * @param object $event
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(object $event): void
    {
        if ($event instanceof AfterWorkerStart) {
            if ($event->server->taskworker) {
                //task进程不能搞这个，因为task进程往往是没开启协程的
                return;
            }
            //记住当前进程的编号
            Common::$workerProcessId = $event->workerId;
        } elseif ($event instanceof MainCoroutineServerStart) {
            //没有编号，就记住进程的pid
            Common::$workerProcessId = getmypid();
        }
        $this->container->get(MainSocketManagerInterface::class)->start();
    }
}
