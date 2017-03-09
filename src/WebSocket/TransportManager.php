<?php

namespace Nuwave\Lighthouse\Subscriptions\WebSocket;

use Exception;
use Nuwave\Lighthouse\Subscriptions\Support\Log;
use Nuwave\Lighthouse\Subscriptions\Support\Exceptions\InvalidSubscriptionQuery;
use Nuwave\Lighthouse\Subscriptions\WebSocket\SubscriptionManager;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class TransportManager implements MessageComponentInterface
{
    /**
     * Connected clients.
     *
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * Subscription manager.
     *
     * @var SubscriptionManager
     */
    protected $subscriptionManager;

    /**
     * Accepted protocols.
     *
     * @var array
     */
    protected $protocols = ['graphql-subscriptions'];

    /**
     * Current instance.
     *
     * @var Chat
     */
    private static $instance;

    /**
     * Create new instance of Transport Manager.
     *
     * @return void
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->subscriptionManager = new SubscriptionManager;

        self::$instance = $this;
    }

    /**
     * Get stored instance.
     *
     * @return self
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Handle new GraphQL Subscription request.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function onOpen(ConnectionInterface $conn)
    {
        try {
            // Attach new subscription manager to connection.
            $this->subscriptionManager->attach($conn);
            // Store the new connection to send messages to later
            $this->clients->attach($conn);

            Log::v('R', $conn, sprintf(
                "new client(%s) on {%s} - [%s] connected clients",
                $conn->resourceId,
                $conn->remoteAddress,
                count($this->clients)
            ));
        } catch (Exception $e) {
            Log::e($e);
        }
    }

    /**
     * Handle new subscription request.
     *
     * @param  ConnectionInterface $conn
     * @param  string $msg
     * @return void
     */
    public function onMessage(ConnectionInterface $conn, $msg)
    {
        try {
            Log::v('R', $conn, "Message from client: $msg");

            $parsedMessage = json_decode($msg, true);

            switch (array_get($parsedMessage, 'type')) {
                case 'init':
                    $this->handleSubscribeInit($conn);
                    break;
                case 'subscription_start':
                    $this->handleSubscriptionStart($conn, $parsedMessage);
                    break;
                case 'subscription_end':
                    $this->handleSubscribeEnd($conn);
                    break;
                case 'keep_alive':
                    $this->handleKeepAlive();
                    break;
                default:
                    $conn->close();
            }
        } catch (Exception $e) {
            Log::e($e);
            $conn->close();
        }
    }

    /**
     * Remove connection.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    public function onClose(ConnectionInterface $conn)
    {
        try {
            // The connection is closed, remove it, as we can no longer send it messages
            $this->subscriptionManager->detach($conn);

            $this->clients->detach($conn);

            Log::v('R', $conn, 'close', "Client({$conn->resourceId}) has disconnected");
        } catch (Exception $e) {
            Log::e($e);
        }
    }

    /**
     * Log and close connection on error.
     *
     * @param  ConnectionInterface $conn
     * @param  Exception $e
     * @return void
     */
    public function onError(ConnectionInterface $conn, Exception $e)
    {
        Log::e($e);
        $conn->close();
    }

    /**
     * Broadcast subscription change.
     *
     * @param  string $msg
     * @return void
     */
    public function broadcast($msg)
    {
        foreach ($this->clients as $conn) {
            if ($manager = $this->subscriptionManager->find($conn)) {
                $manager->broadcast($conn, $msg);
            }
        }
    }

    /**
     * Log all subscriptions.
     *
     * @return void
     */
    public function log()
    {
        foreach ($this->clients as $conn) {
            if ($manager = $this->subscriptionManager->find($conn)) {
                Log::v('S', $conn, "Subscriptions: " . $manager->all()->toJson());
            } else {
                Log::v('S', $conn, "No subscriptions for connection");
            }
        }
    }

    /**
     * Handle initialization of subscription.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    protected function handleSubscribeInit(ConnectionInterface $conn)
    {
        // TODO: check if there is a onConnect callback registered, and if so run it.
        // if it fails, send subscription init failed message.
        $this->sendMessage($conn, [
            'type' => 'init_success',
        ]);
    }

    /**
     * Handle start of subscription.
     *
     * @param  ConnectionInterface $conn
     * @param  array $msg
     * @return void
     */
    protected function handleSubscriptionStart(ConnectionInterface $conn, array $msg)
    {
        try {
            // TODO: Create onSubscription callback option and check if true.
            $baseParams = [
                'query' => array_get($msg, 'query'),
                'variables' => array_get($msg, 'variables'),
                'operationName' => array_get($msg, 'operationName'),
            ];

            $this->subscriptionManager->subscribe(
                $conn,
                array_get($msg, 'id', 0),
                $baseParams
            );

            Log::v('S', $conn, "Subscription: " . json_encode($baseParams));

            $this->sendMessage($conn, [
                'type' => 'subscription_success',
                'id' => array_get($msg, 'id', 0),
            ]);
        } catch (InvalidSubscriptionQuery $e) {
            $this->sendMessage($conn, [
                'type' => 'subscription_fail',
                'id' => array_get($msg, 'id', 0),
                'payload' => [
                    'errors' => $e->getErrors(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->sendMessage($conn, [
                'type' => 'subscription_fail',
                'id' => array_get($msg, 'id', 0),
                'payload' => [
                    'errors' => ['message' => $e->getMessage()],
                ],
            ]);
        }
    }

    /**
     * Handle end of subscription.
     *
     * @param  ConnectionInterface $conn
     * @return void
     */
    protected function handleSubscribeEnd(ConnectionInterface $conn)
    {
        // TODO: Implement unsubscribe flow.
        Log::v('S', $conn, "unsubscribe from query");
    }

    /**
     * Send response to keep connection(s) alive.
     *
     * @return void
     */
    public function handleKeepAlive()
    {
        foreach ($this->clients as $client) {
            $this->sendMessage($client, [
                'type' => 'keepalive',
            ], false);
        }
    }

    /**
     * Send message to connection.
     *
     * @param  ConnectionInterface $conn
     * @param  array $message
     * @param  boolean $log
     * @return void
     */
    protected function sendMessage(ConnectionInterface $conn, array $message, $log = true)
    {
        $connMessage = json_encode($message);

        if ($log) {
            Log::v('S', $conn, "sending message \"{$connMessage}\"");
        }

        $conn->send($connMessage);
    }
}
