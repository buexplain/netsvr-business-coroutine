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

use NetsvrBusiness\Contract\TaskSocketFactoryInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Exception\SocketConnectException;
use Psr\Log\LoggerInterface;
use function Hyperf\Support\make;

/**
 *
 */
class TaskSocketFactory implements TaskSocketFactoryInterface
{
    /**
     * @param string $logPrefix
     * @param LoggerInterface $logger
     * @param string $host
     * @param int $port
     * @param int $sendReceiveTimeout
     * @param int $connectTimeout
     */
    public function __construct(
        protected string          $logPrefix,
        protected LoggerInterface $logger,
        protected string          $host,
        protected int             $port,
        protected int             $sendReceiveTimeout,
        protected int             $connectTimeout,
    )
    {
    }

    /**
     *
     * @param TaskSocketPoolInterface $pool
     * @return TaskSocketInterface
     */
    public function make(TaskSocketPoolInterface $pool): TaskSocketInterface
    {
        $socket = make(TaskSocketInterface::class, [
            'logPrefix' => $this->logPrefix,
            'logger' => $this->logger,
            'host' => $this->host,
            'port' => $this->port,
            'sendReceiveTimeout' => $this->sendReceiveTimeout,
            'connectTimeout' => $this->connectTimeout,
            'pool' => $pool,
        ]);
        if (!$socket->connect()) {
            throw new SocketConnectException(sprintf('connect to %s:%s failed', $this->host, $this->port));
        }
        return $socket;
    }
}