<?php

namespace Nuwave\Lighthouse\Subscriptions\Support\Console\Commands;

use Illuminate\Console\Command;

class WebSocketServerCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'lighthouse:subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Lighthouse GraphQL Subscription server.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        app('graphql-subscriptions')->run();
    }
}
