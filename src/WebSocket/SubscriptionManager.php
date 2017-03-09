<?php

namespace Nuwave\Lighthouse\Subscriptions\WebSocket;

use Ratchet\ConnectionInterface;

class SubscriptionManager
{
    /**
     * Current connections.
     *
     * @var \Illuminate\Support\Collection
     */
    private $connections;

    /**
     * Create new instance of connection manager.
     *
     * @return void
     */
    public function __construct()
    {
        $this->connections = collect([]);
    }

    /**
     * Attach new connection.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function attach(ConnectionInterface $conn)
    {
        $this->connections->put($conn->resourceId, new Subscriber($conn));
    }

    /**
     * Detach connection.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function detach(ConnectionInterface $conn)
    {
        $this->connections->forget($conn->resourceId);
    }

    /**
     * Find subscriber instance.
     *
     * @param  ConnectionInterface $conn
     * @return Subscriber
     */
    public function find(ConnectionInterface $conn)
    {
        return $this->connections->get($conn->resourceId);
    }

    /**
     * Add subscription to subscriber.
     *
     * @param  ConnectionInterface $conn
     * @param  integer $subId
     * @param  array $params
     * @return boolean
     */
    public function subscribe(ConnectionInterface $conn, $subId, array $params)
    {
        if ($subscriber = $this->find($conn)) {
            $subscriber->subscribe($subId, $params);

            return true;
        }

        return false;
    }

    /**
     * Remove subscription to subscriber.
     *
     * @param  ConnectionInterface $conn
     * @param  integer $subId
     * @param  array $params
     * @return boolean
     */
    public function unsubscribe(ConnectionInterface $conn, $subId, array $params)
    {
        if ($subscriber = $this->find($conn)) {
            $subscriber->unsubscribe($subId, $params);

            return true;
        }

        return false;
    }
}
