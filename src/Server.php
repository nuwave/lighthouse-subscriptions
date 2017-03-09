<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Support\Parser;

class Server
{
    /**
     * Port WebSocket server is listening on.
     *
     * @var integer
     */
    protected $port;

    /**
     * Keep alive interval.
     *
     * @var integer
     */
    protected $keepAliveInterval;

    /**
     * Create new instance of websocket server.
     *
     * @param integer $port
     * @param integer $keepAliveInterval
     */
    public function __construct($port, $keepAliveInterval)
    {
        $this->port = $port;
        $this->keepAliveInterval = $keepAliveInterval;
    }

    /**
     * Run GraphQL Subscription Server.
     *
     * @return void
     */
    public function run()
    {
        $loop = \React\EventLoop\Factory::create();
        $wsServer = $this->runServer($loop);

        $this->log($loop);
        $this->runKeepAlive();

        $loop->addPeriodicTimer(10, function () {
            \Nuwave\Lighthouse\Subscriptions\TransportManager::getInstance()->log();
        });

        $loop->run();
    }

    /**
     * Run WS Server.
     *
     * @param  \React\EventLoop\StreamSelectLoop $loop
     * @return \Ratchet\Server\IoServer
     */
    protected function runServer($loop)
    {
        $parser = new Parser();
        $pusher = new \Nuwave\Lighthouse\Subscriptions\Pusher($loop);
        $webSock = new \React\Socket\Server($loop);

        $webSock->listen($this->port);

        return new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer(
                    new \Nuwave\Lighthouse\Subscriptions\TransportManager($pusher)
                )
            ),
            $webSock
        );
    }

    /**
     * Run keep alive.
     *
     * @return void
     */
    protected function runKeepAlive()
    {
        if ($this->keepAliveInterval > 0) {
            $loop->addPeriodicTimer($this->keepAliveInterval, function () {
                \Redis::publish('graphql.subscription', json_encode([
                    'type' => 'keepalive'
                ]));
            });
        }
    }

    /**
     * Log start server message.
     *
     * @param  mixed $loop
     * @return void
     */
    protected function log($loop)
    {
        \Nuwave\Lighthouse\Subscriptions\Support\Log::v(
            ' ',
            $loop,
            "Starting Websocket Service on port " . $this->port
        );
    }
}
