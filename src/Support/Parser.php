<?php

namespace Nuwave\Lighthouse\Subscriptions\Support;

use GraphQL\Language\Source;
use GraphQL\Language\Parser as GraphQLParser;
use GraphQL\Validator\DocumentValidator;
use Nuwave\Lighthouse\Subscriptions\Support\Exceptions\InvalidSubscriptionQuery;

class Parser
{
    /**
     * Current instance.
     *
     * @var self
     */
    private static $instance;

    /**
     * Create new instance of parser.
     *
     * @return void
     */
    public function __construct()
    {
        self::$instance = $this;
        echo "Creating a new instance\n";
    }

    /**
     * Get stored instance.
     *
     * @return self
     */
    public static function getInstance()
    {
        return self::$instance ?: new static;
    }

    /**
     * Validate subscription query.
     *
     * @param  string $query
     * @return boolean
     */
    public function validate($query = '')
    {
        // TODO: Push this method to the GraphQL class.
        $schema = app('graphql')->buildSchema();
        $source = new Source($query, 'GraphQL request');
        $documentNode = GraphQLParser::parse($source);
        $validationErrors = DocumentValidator::validate($schema, $documentNode);

        // TODO: Also check that query is on a subscription field.

        if (!empty($validationErrors)) {
            $errors = collect($validationErrors)->map(function ($error) {
                return ['message' => $error->message];
            })->toArray();

            $exception = new InvalidSubscriptionQuery("Invalid GraphQL Subscription Query");
            $exception->setErrors($errors);

            throw $exception;
        }

        return true;
    }

    /**
     * Get query name for subscription.
     *
     * @param  string $query
     * @return string
     */
    public function subscriptionName($query = '')
    {
        $schema = app('graphql')->buildSchema();
        $source = new Source($query, 'GraphQL request');
        $documentNode = GraphQLParser::parse($source);

        return collect($documentNode->definitions)->reduce(function ($carry, $item) {
            return $item instanceof \GraphQL\Language\AST\OperationDefinitionNode
                ? $item->name->value
                : $carry;
        }, '');
    }

    /**
     * Get GraphQL Query instance that handles subscription.
     *
     * @param  string $query
     * @return \Nuwave\Lighthouse\Support\Definition\GraphQLQuery
     */
    public function getSubscription($query = '')
    {
        $subscription = self::subscriptionName($query);

        $query = app('graphql')->subscriptions()->first(function ($query) use ($subscription) {
            return $subscription === $query->name;
        });

        if (is_null($query)) {
            $exception = new InvalidSubscriptionQuery("Invalid GraphQL Subscription Query");
            $exception->setErrors(['message' => "Unable to find query [{$query}]"]);

            throw $exception;
        }

        return app($query->namespace);
    }
}
