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

use Hyperf\Collection\Arr;
use Hyperf\Contract\StdoutLoggerInterface;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Socket\MainSocketManager;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

/**
 *
 */
class MainSocketManagerFactory
{
    protected MainSocketManagerInterface|null $manger = null;

    /**
     * @param EventInterface $event
     * @param StdoutLoggerInterface $logger
     */
    public function __construct(protected EventInterface $event, protected StdoutLoggerInterface $logger)
    {
    }

    /**
     */
    public function __invoke(): ?MainSocketManagerInterface
    {
        if ($this->manger) {
            return $this->manger;
        }
        $logPrefix = sprintf('MainSocket#%d', Common::$workerProcessId);
        $config = config('business', []);
        $netsvr = Arr::pull($config, 'netsvr', []);
        if (empty($netsvr)) {
            return null;
        }
        //将所以准备好的连接存储到连接管理器中
        $manger = new MainSocketManager();
        foreach ($netsvr as $item) {
            //将网关的特定参数与公共参数进行合并，网关的特定参数覆盖公共参数
            $item = array_merge($config, $item);
            //再补充其它参数
            $item['logPrefix'] = $logPrefix;
            $item['logger'] = $this->logger;
            /**
             * @var SocketInterface $socket
             */
            $socket = make(SocketInterface::class, $item);
            $item['socket'] = $socket;
            $item['event'] = $this->event;
            /**
             * @var MainSocketInterface $mainSocket
             */
            $mainSocket = make(MainSocketInterface::class, $item);
            $manger->set($mainSocket);
        }
        $this->manger = $manger;
        return $manger;
    }
}