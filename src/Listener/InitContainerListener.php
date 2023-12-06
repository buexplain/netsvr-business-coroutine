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
use NetsvrBusiness\Common;
use Psr\Container\ContainerInterface;

/**
 * 初始化容器，无需监听任何事件，只需在初始化本类的时候，设置一下本包的容器
 */
class InitContainerListener implements ListenerInterface
{
    /**
     * @param ContainerInterface $container
     */
    public function __construct(protected ContainerInterface $container)
    {
        if (is_null(Common::$container)) {
            Common::$container = $this->container;
        }
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [];
    }

    /**
     * @param object $event
     * @return void
     */
    public function process(object $event): void
    {
    }
}
