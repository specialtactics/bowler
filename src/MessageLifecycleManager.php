<?php

namespace Vinelab\Bowler;

use Closure;
use Exception;
use Illuminate\Contracts\Logging\Log;
use PhpAmqpLib\Message\AMQPMessage;

class MessageLifecycleManager
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var array
     */
    protected $beforePublish = [];

    /**
     * @var array
     */
    protected $published = [];

    /**
     * @var array
     */
    protected $beforeConsume = [];

    /**
     * @var array
     */
    protected $consumed = [];

    /**
     * MessageLifecycleManager constructor.
     * @param  Log  $logger
     */
    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param  Closure  $callback
     * @return void
     */
    public function beforePublish(Closure $callback)
    {
        $this->beforePublish[] = $callback;
    }

    /**
     * @param  Closure  $callback
     * @return void
     */
    public function published(Closure $callback)
    {
        $this->published[] = $callback;
    }

    /**
     * @param  Closure  $callback
     * @return void
     */
    public function beforeConsume(Closure $callback)
    {
        $this->beforeConsume[] = $callback;
    }

    /**
     * @param  Closure  $callback
     * @return void
     */
    public function consumed(Closure $callback)
    {
        $this->consumed[] = $callback;
    }

    /**
     * @param  AMQPMessage  $msg
     * @param  string  $exchangeName
     * @param  null  $routingKey
     * @return void
     */
    public function triggerBeforePublish(AMQPMessage $msg, string $exchangeName, $routingKey = null)
    {
        foreach ($this->beforePublish as $callback) {
            $this->executeCallback($msg, $callback, func_get_args());
        }
    }

    /**
     * @param  AMQPMessage  $msg
     * @param  string  $exchangeName
     * @param  null  $routingKey
     * @return void
     */
    public function triggerPublished(AMQPMessage $msg, string $exchangeName, $routingKey = null)
    {
        foreach ($this->published as $callback) {
            $this->executeCallback($msg, $callback, func_get_args());
        }
    }

    /**
     * @param  AMQPMessage  $msg
     * @param  string  $queueName
     * @param  string  $handlerClass
     * @return void
     */
    public function triggerBeforeConsume(AMQPMessage $msg, string $queueName, string $handlerClass)
    {
        foreach ($this->beforeConsume as $callback) {
            $this->executeCallback($msg, $callback, func_get_args());
        }
    }

    /**
     * @param  AMQPMessage  $msg
     * @param  string  $queueName`
     * @param  string  $handlerClass
     * @param  Ack  $ack
     * @return void
     */
    public function triggerConsumed(AMQPMessage $msg, string $queueName, string $handlerClass, Ack $ack)
    {
        foreach ($this->consumed as $callback) {
            $this->executeCallback($msg, $callback, func_get_args());
        }
    }

    /**
     * @param  AMQPMessage  $msg
     * @param  Closure  $callback
     * @param  array  $args
     * @return mixed
     */
    protected function executeCallback(AMQPMessage $msg, Closure $callback, array $args)
    {
        try {
            call_user_func_array($callback, $args);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }
}
