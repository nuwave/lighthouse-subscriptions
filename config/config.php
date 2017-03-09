<?php

return [
    /**
     * The port the web socket server will listen on.
     */
    'port' => env('LIGHTHOUSE_SUBSCRIPTIONS_PORT', 9000),

    /**
     * Set your keep alive interval here
     * if your connection requires it.
     */
    'keep_alive' => env('LIGHTHOUSE_SUBSCRIPTIONS_KEEPALIVE', null),
];
