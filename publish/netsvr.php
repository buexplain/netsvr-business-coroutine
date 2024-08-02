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

use Netsvr\Event;
use function Hyperf\Support\env;

return [
    //如果台网关服务机器承载不了业务的websocket连接数，可以再部署一台网关服务机器，这里支持配置多个网关服务，处理多个网关服务的websocket消息
    'netsvr' => [
        [
            //netsvr网关的worker服务器监听的tcp地址
            'workerAddr' => (string)env('NETSVR_WORKER_ADDR', '127.0.0.1:6061'),
            //该参数表示接下来，需要网关服务的worker服务器开启多少协程来处理mainSocket连接的请求
            'processCmdGoroutineNum' => 25,
            //该参数表示接下来，需要网关服务的worker服务器转发如下事件给到business进程的mainSocket连接
            'events' => Event::OnOpen | Event::OnClose | Event::OnMessage,
        ],
    ],
    //socket读写网关数据的超时时间，单位秒
    'sendReceiveTimeout' => 5,
    //连接到网关的超时时间，单位秒
    'connectTimeout' => 5,
    //business进程向网关的worker服务器发送的心跳消息，这个字符串与网关的worker服务器的配置要一致，如果错误，网关的worker服务器是会强制关闭连接的
    'workerHeartbeatMessage' => '~6YOt5rW35piO~',
    //维持心跳的间隔时间，单位毫秒
    'heartbeatIntervalMillisecond' => 25 * 1000,
    //与网关保持连接的socket的心跳间隔时间
    //task连接的连接池的最大连接数
    'taskSocketPoolMaxConnections' => 25,
    //task连接的连接池的获取连接时的超时时间，单位毫秒
    'taskSocketPoolWaitTimeoutMillisecond' => 3000,
];