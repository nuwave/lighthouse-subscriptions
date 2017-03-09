<?php

namespace Nuwave\Lighthouse\Subscriptions\WebSocket;

use Nuwave\Lighthouse\Subscriptions\Support\Log;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class Pusher implements WampServerInterface
{
    /**
     * Create instance of redis pusher.
     *
     * @param \React\EventLoop\StreamSelectLoop $loop
     */
    public function __construct($loop)
    {
        $redis_host = config('database.redis.default.host');
        $redis_port = config('database.redis.default.port');

        $client = new \Predis\Async\Client('tcp://'. $redis_host. ':'. $redis_port, $loop);

        $client->connect(function ($client) use ($loop) {
            $client->pubSubLoop('graphql.subscription', function ($event) use ($loop) {
                $payload = json_decode($event->payload, true);

                if (array_get($payload, 'type') == 'keepalive') {
                    TransportManager::getInstance()->handleKeepAlive();
                } else {
                    TransportManager::getInstance()->broadcast($payload);
                }
            });

            Log::v(' ', $loop, "Connected to Redis.");
        });
    }

    public function onOpen(ConnectionInterface $conn)
    {
    }

    public function onClose(ConnectionInterface $conn)
    {
    }

    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
    }

    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }
}
