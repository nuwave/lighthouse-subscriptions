<?php

namespace Nuwave\Lighthouse\Subscriptions\Support\Broadcasters;

use Illuminate\Broadcasting\Broadcasters\RedisBroadcaster as Broadcaster;

class RedisBroadcaster extends Broadcaster
{
    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $connection = $this->redis->connection($this->connection);

        foreach ($this->formatChannels($channels) as $channel) {
            $payload = json_encode([
                'event' => $channel,
                'data' => $payload,
            ]);

            $connection->publish('graphql.subscription', $payload);
        }
    }
}
