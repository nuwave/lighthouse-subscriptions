<?php

return [
    /**
     * The port the web socket server will listen on.
     */
    'port' => env('WEBSOCKET_PORT', 9000),

    /**
     * Set your keep alive interval here
     * if your connection requires it.
     */
    'keep_alive' => env('WEBSOCKET_KEEPALIVE', null),
];
