<?php

namespace Vinelab\Bowler\Tests;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Support\Arr;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Vinelab\Bowler\Ack;
use Vinelab\Bowler\MessageLifecycleManager;

class MessageLifecycleManagerTest extends TestCase
{
    /**
     * @var Log|Mockery\MockInterface
     */
    private $logger;

    /**
     * @var Repository
     */
    private $config;

    protected function setUp()
    {
        parent::setUp();
        $this->logger = Mockery::spy(Log::class);
        $this->config = $this->app->make('config');
    }

    public function test_before_publish()
    {
        $exec = false;

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->beforePublish(function (AMQPMessage $msg, $exchangeName, $routingKey) use (&$exec) {
            $exec = true;

            $this->assertEquals('example', $msg->body);
            $this->assertEquals('logs', $exchangeName);
            $this->assertEquals('critical', $routingKey);

            $amqpTable = Arr::get($msg->get_properties(), 'application_headers', new AMQPTable);
            $amqpTable->set('x-custom-header', '523292956497346007734586');

            $msg->set('application_headers', $amqpTable);
        });

        $msg = new AMQPMessage('example');

        $lifecycle->triggerBeforePublish($msg, 'logs', 'critical');

        $this->assertTrue($exec, 'Failed asserting that registered callback was executed');

        /** @var AMQPTable $amqpTable */
        $amqpTable = Arr::get($msg->get_properties(), 'application_headers', new AMQPTable);
        $this->assertEquals('523292956497346007734586', Arr::get($amqpTable->getNativeData(), 'x-custom-header'));
    }

    public function test_published()
    {
        $exec = false;

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->published(function ($msg, $exchangeName, $routingKey) use (&$exec) {
            $exec = true;

            $this->assertEquals('example', $msg->body);
            $this->assertEquals('logs', $exchangeName);
            $this->assertEquals('critical', $routingKey);
        });

        $msg = new AMQPMessage('example');

        $lifecycle->triggerPublished($msg, 'logs', 'critical');

        $this->assertTrue($exec, 'Failed asserting that registered callback was executed');
    }

    public function test_before_consume()
    {
        $exec = false;

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->beforeConsume(function (AMQPMessage $msg, $exchangeName, $handlerClass) use (&$exec) {
            $exec = true;

            $this->assertEquals('example', $msg->body);
            $this->assertEquals('logs', $exchangeName);
            $this->assertEquals('ProcessLogsHandler', $handlerClass);

            $amqpTable = Arr::get($msg->get_properties(), 'application_headers', new AMQPTable);
            $amqpTable->set('x-custom-header', '523292956497346007734586');

            $msg->set('application_headers', $amqpTable);
        });

        $msg = new AMQPMessage('example');

        $lifecycle->triggerBeforeConsume($msg, 'logs', 'ProcessLogsHandler');

        $this->assertTrue($exec, 'Failed asserting that registered callback was executed');

        /** @var AMQPTable $amqpTable */
        $amqpTable = Arr::get($msg->get_properties(), 'application_headers', new AMQPTable);
        $this->assertEquals('523292956497346007734586', Arr::get($amqpTable->getNativeData(), 'x-custom-header'));
    }

    public function test_consumed()
    {
        $exec = false;

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->consumed(function ($msg, $exchangeName, $handlerClass, $ack) use (&$exec) {
            $exec = true;

            $this->assertEquals('example', $msg->body);
            $this->assertEquals('logs', $exchangeName);
            $this->assertEquals('ProcessLogsHandler', $handlerClass);

            $this->assertEquals(Ack::MODE_REJECT, $ack->mode);
            $this->assertTrue($ack->requeue);
            $this->assertFalse($ack->multiple);
        });

        $lifecycle->triggerConsumed(
            new AMQPMessage('example'),
            'logs',
            'ProcessLogsHandler',
            new Ack(Ack::MODE_REJECT, true, false)
        );

        $this->assertTrue($exec, 'Failed asserting that registered callback was executed');
    }

    public function test_suppress_error_thrown_from_callback()
    {
        $e = new RuntimeException('Oops!');

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->beforePublish(function ($msg, $exchangeName, $routingKey) use ($e) {
            throw $e;
        });

        $lifecycle->triggerBeforePublish(new AMQPMessage('example'), 'logs', 'critical');

        $this->logger->shouldHaveReceived('error')->once()->with($e->getMessage(), ['exception' => $e]);
    }

    public function test_disable_suppressing_errors()
    {
        $this->expectException(RuntimeException::class);

        $lifecycle = new MessageLifecycleManager($this->logger, $this->config);
        $lifecycle->beforePublish(function ($msg, $exchangeName, $routingKey) {
            throw new RuntimeException('Oops!');
        });

        $this->config->set('bowler.lifecycle_hooks.fail_on_error', true);

        $lifecycle->triggerBeforePublish(new AMQPMessage('example'), 'logs', 'critical');
    }
}
