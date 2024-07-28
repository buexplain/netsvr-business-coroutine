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
use NetsvrBusiness\Contract\TaskSocketFactoryInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Socket\TaskSocketPoolManger;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

/**
 *
 */
class TaskSocketPoolMangerFactory
{
    protected TaskSocketPoolManger|null $manger = null;

    /**
     * @param StdoutLoggerInterface $logger
     */
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    /**
     * @return TaskSocketPoolMangerInterface|null
     */
    public function __invoke(): ?TaskSocketPoolMangerInterface
    {
        if ($this->manger) {
            return $this->manger;
        }
        $logPrefix = sprintf('TaskSocket#%d', Common::$workerProcessId);
        $config = config('netsvr', []);
        $netsvr = Arr::pull($config, 'netsvr', []);
        if (empty($netsvr)) {
            return null;
        }
        $manger = new TaskSocketPoolManger();
        $this->manger = $manger;
        foreach ($netsvr as $item) {
            //将网关的特定参数与公共参数进行合并，网关的特定参数覆盖公共参数
            $item = array_merge($config, $item);
            //再补充其它参数
            $item['logPrefix'] = $logPrefix;
            $item['logger'] = $this->logger;
            /**
             * @var TaskSocketFactoryInterface $factory
             */
            $factory = make(TaskSocketFactoryInterface::class, $item);
            $pool = make(TaskSocketPoolInterface::class, [
                'logPrefix' => $item['logPrefix'],
                'logger' => $item['logger'],
                'heartbeatIntervalMillisecond' => $item['heartbeatIntervalMillisecond'],
                'size' => $item['taskSocketPoolMaxConnections'],
                'factory' => $factory,
                'workerAddr' => $item['workerAddr'],
                'workerHeartbeatMessage' => $item['workerHeartbeatMessage'],
                'waitTimeoutMillisecond' => $item['taskSocketPoolWaitTimeoutMillisecond'],
            ]);
            $manger->addSocket($pool);
        }
        return $manger;
    }
}