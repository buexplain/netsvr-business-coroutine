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

namespace App;

use Hyperf\Contract\StdoutLoggerInterface;
use NetsvrProtocol\ConnClose;
use NetsvrProtocol\ConnOpen;
use NetsvrProtocol\Transfer;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\NetBus;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 这个是默认实现，业务层需要自己实现 HandleInterface 接口
 */
class Event implements EventInterface
{
    protected StdoutLoggerInterface $logger;

    /**
     * @param StdoutLoggerInterface $logger
     */
    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 客户连接打开
     * @param ConnOpen $connOpen
     * @return void
     */
    public function onOpen(ConnOpen $connOpen): void
    {
        $this->logger->info('onOpen ' . $connOpen->serializeToJsonString());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onMessage(Transfer $transfer): void
    {
        NetBus::singleCast($transfer->getUniqId(), $transfer->getData());
    }

    /**
     * 客户连接关闭
     * @param ConnClose $connClose
     * @return void
     */
    public function onClose(ConnClose $connClose): void
    {
        $this->logger->info('onClose ' . $connClose->serializeToJsonString());
    }
}