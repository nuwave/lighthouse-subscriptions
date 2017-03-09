<?php

namespace Nuwave\Lighthouse\Subscriptions\WebSocket;

use Nuwave\Lighthouse\Subscriptions\Support\Parser;
use Nuwave\Lighthouse\Subscriptions\Support\Exceptions\UnprocessableSubscription;
use Ratchet\ConnectionInterface;

class Subscriber
{
    /**
     * Websocket connection.
     *
     * @var ConnectionInterface
     */
    private $conn;

    /**
     * Authorized user attached to connection.
     *
     * @var mixed
     */
    private $auth;

    /**
     * Connection context.
     *
     * @var mixed
     */
    private $context;

    /**
     * Subsribed queries.
     *
     * @var \Illuminate\Support\Collection
     */
    private $subscriptions;

    /**
     * Create new instance of subscriber.
     *
     * @return void
     */
    public function __construct(ConnectionInterface $conn)
    {
        $this->conn = $conn;
        $this->subscriptions = collect([]);
    }

    /**
     * Get all subscriptions for connection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all()
    {
        return $this->subscriptions;
    }

    /**
     * Add query to connection's subscriptions.
     *
     * @param  integer $subId
     * @param  array  $params
     * @return void
     */
    public function subscribe($subId, array $params)
    {
        if ($this->subscriptions->has($subId)) {
            $this->subscriptions->forget($subId);
        }

        $query = array_get($params, 'query', '');
        $args = array_get($params, 'variables', []);
        $context = $this->getContext($query, $args);
        $triggerName = Parser::getInstance()->subscriptionName($query);

        $this->subscriptions->put(
            $subId,
            compact('context', 'args', 'query', 'triggerName')
        );
    }

    /**
     * Remove query from connection's subscriptions.
     *
     * @param  integer $subId
     * @return void
     */
    public function unsubscribe($subId)
    {
        $this->subscriptions->forget($subId);
    }

    /**
     * Broadcast message to connection.
     *
     * @param  ConnectionInterface $conn
     * @param  array $message
     * @return void
     */
    public function broadcast(ConnectionInterface $conn, array $message)
    {
        $triggerName = array_get($message, 'event', '');

        $this->subscriptions->filter(function ($subscription) use ($triggerName) {
            return $subscription['triggerName'] === $triggerName;
        })->each(function ($subscription, $subId) use ($conn) {
            $conn->send(json_encode([
                'type' => 'subscription_data',
                'id' => $subId,
                'payload' => app('graphql')->execute(
                    $subscription['query'],
                    $subscription['context'],
                    $subscription['args']
                )
            ]));
        });
    }

    /**
     * Get context for subscription.
     *
     * @param  string $query
     * @param  array  $variables
     * @return mixed
     */
    protected function getContext($query, array $variables)
    {
        Parser::getInstance()->validate($query);

        $query = Parser::getInstance()->getSubscription($query);
        $context = $query->onSubscribe($variables);

        if (!(bool) $context) {
            $subscription = Parser::getInstance()->subscriptionName($query);

            $exception = new UnprocessableSubscription("Unable to subscribe");
            $exception->setErrors(['message' => "Unable to subscribe to [{$subscription}]"]);

            throw new $exception;
        }

        return $context;
    }
}
