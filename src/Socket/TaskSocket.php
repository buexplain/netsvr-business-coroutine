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

namespace NetsvrBusiness\Socket;

use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * 与网关连接的任务socket，用于：
 * 1. business请求网关，需要网关响应指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程请求网关网关处理完毕再响应给业务进程的指令
 * 2. business请求网关，不需要网关响应指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程单向请求网关的指令
 */
class TaskSocket extends Socket implements TaskSocketInterface
{
    /**
     * @var TaskSocketPoolInterface 本对象所属的连接池
     */
    protected TaskSocketPoolInterface $pool;

    /**
     * @param string $host netsvr网关的worker服务器监听的主机
     * @param int $port netsvr网关的worker服务器监听的端口
     * @param int $sendReceiveTimeout 读写数据超时，单位秒
     * @param int $connectTimeout 连接到服务端超时，单位秒
     */
    public function __construct(
        string                  $logPrefix,
        LoggerInterface         $logger,
        string                  $host,
        int                     $port,
        int                     $sendReceiveTimeout,
        int                     $connectTimeout,
        TaskSocketPoolInterface $pool
    )
    {
        parent::__construct($logPrefix, $logger, $host, $port, $sendReceiveTimeout, $connectTimeout);
        $this->pool = $pool;
    }

    /**
     * 将自己归还给连接池
     * @return void
     */
    public function release(): void
    {
        if ($this->isConnected()) {
            $this->pool->release($this);
        } else {
            $this->pool->release(null);
        }
    }
}
