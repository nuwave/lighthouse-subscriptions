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
     * Subsribed queries.
     *
     * @var \Illuminate\Support\Collection
     */
    private $subscriptions;

    /**
     * Connection context.
     *
     * @var \Illuminate\Support\Collection
     */
    private $context;

    /**
     * Create new instance of subscriber.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function __construct(ConnectionInterface $conn)
    {
        $this->conn = $conn;
        $this->subscriptions = collect([]);
        $this->context = collect([]);
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
     * @param  mixed  $request
     * @return void
     */
    public function subscribe($subId, array $params, $request)
    {
        if ($this->subscriptions->has($subId)) {
            $this->subscriptions->forget($subId);
        }

        $query = array_get($params, 'query', '');
        $args = array_get($params, 'variables', []);

        $this->validateSubscription($query, $args, $request);

        $triggerName = Parser::getInstance()->subscriptionName($query);

        $this->subscriptions->put(
            $subId,
            compact('args', 'query', 'triggerName')
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
        })->filter(function ($subscription) {
            return $this->filterQuery($subscription);
        })->each(function ($subscription, $subId) use ($conn) {
            $conn->send(json_encode([
                'type' => 'subscription_data',
                'id' => $subId,
                'payload' => app('graphql')->execute(
                    $subscription['query'],
                    $this->context,
                    $subscription['args']
                )
            ]));
        });
    }

    /**
     * Store auth for connection.
     *
     * @param  mixed $auth
     * @return void
     */
    public function authorize($auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle new subscription request.
     *
     * @param  string $query
     * @param  array  $variables
     * @param  mixed  $request
     * @return mixed
     */
    protected function validateSubscription($query, array $variables, $request)
    {
        Parser::getInstance()->validate($query);

        $query = Parser::getInstance()->getSubscription($query);

        if (!$query->canSubscribe($variables, $request, $this->context)) {
            $subscription = Parser::getInstance()->subscriptionName($query);

            $exception = new UnprocessableSubscription("Unable to subscribe");
            $exception->setErrors(['message' => "Unable to subscribe to [{$subscription}]"]);

            throw new $exception;
        }
    }

    /**
     * Get context for for query.
     *
     * @param  array  $subscription
     * @return mixed
     */
    protected function filterQuery(array $subscription)
    {
        $query = Parser::getInstance()->getSubscription(
            array_get($subscription, 'query', '')
        );

        return $query->filter(
            array_get($subscription, 'args', []),
            $this->context
        );
    }
}
