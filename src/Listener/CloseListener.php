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

use Hyperf\Command\Event\AfterExecute;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Framework\Event\OnWorkerExit;
use Hyperf\Process\Event\AfterCoroutineHandle;
use Hyperf\Process\Event\AfterProcessHandle;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Server\Event\CoroutineServerStop;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 销毁本包建立的各种协程与socket连接
 */
class CloseListener implements ListenerInterface
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
            //命令进程行结束
            AfterExecute::class,
            //swoole异步风格服务器进程结束，详细介绍请看：https://wiki.swoole.com/#/server/events?id=onworkerexit
            OnWorkerExit::class,
            //swoole异步风格服务器的自定义进程结束
            AfterProcessHandle::class,
            //swoole协程风格服务器的自定义进程结束
            AfterCoroutineHandle::class,
            //swoole协程风格服务器进程结束，swow服务器进程结束
            CoroutineServerStop::class,
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
        if (!Coroutine::inCoroutine()) {
            Coroutine::create(function () {
                $this->container->get(MainSocketManagerInterface::class)?->close();
                $this->container->get(TaskSocketPoolMangerInterface::class)?->close();
            });
        } else {
            $this->container->get(MainSocketManagerInterface::class)?->close();
            $this->container->get(TaskSocketPoolMangerInterface::class)?->close();
        }
    }
}
