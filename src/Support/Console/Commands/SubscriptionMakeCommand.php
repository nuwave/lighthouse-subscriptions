<?php

namespace Nuwave\Lighthouse\Subscriptions\Support\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class SubscriptionMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a GraphQL subscription.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Subscription';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/subscription.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('lighthouse-subscriptions.namespace');
    }
}
