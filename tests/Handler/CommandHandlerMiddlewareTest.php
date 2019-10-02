<?php

declare(strict_types=1);

namespace League\Tactician\Tests\Handler;

use League\Tactician\Handler\CanNotInvokeHandler;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\Mapping\CommandToHandlerMapping;
use League\Tactician\Tests\Fixtures\Command\CompleteTaskCommand;
use League\Tactician\Tests\Fixtures\Handler\ConcreteMethodsHandler;
use League\Tactician\Tests\Fixtures\Handler\DynamicMethodsHandler;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

class CommandHandlerMiddlewareTest extends TestCase
{
    /** @var CommandHandlerMiddleware */
    private $middleware;
    /** @var ContainerInterface&MockObject */
    private $container;
    /** @var CommandToHandlerMapping&MockObject */
    private $mapping;

    protected function setUp(): void
    {
        $this->middleware = new CommandHandlerMiddleware(
            $this->container = $this->createMock(ContainerInterface::class),
            $this->mapping = $this->createMock(CommandToHandlerMapping::class)
        );
    }

    public function testHandlerIsExecuted(): void
    {
        $command = new CompleteTaskCommand();

        $handler = $this->createMock(ConcreteMethodsHandler::class);
        $handler
            ->expects(self::once())
            ->method('handleTaskCompletedCommand')
            ->with($command)
            ->willReturn('a-return-value');

        $this->container
            ->method('get')
            ->with(ConcreteMethodsHandler::class)
            ->willReturn($handler);

        $this->mapping
            ->method('getMethodName')
            ->with(CompleteTaskCommand::class)
            ->willReturn('handleTaskCompletedCommand');

        $this->mapping
            ->method('getClassName')
            ->with(CompleteTaskCommand::class)
            ->willReturn(ConcreteMethodsHandler::class);

        self::assertEquals('a-return-value', $this->middleware->execute($command, $this->mockNext()));
    }

    public function testMissingMethodOnHandlerObjectIsDetected(): void
    {
        $command = new CompleteTaskCommand();

        $this->container
            ->method('get')
            ->willReturn(new stdClass());

        $this->mapping
            ->method('getClassName')
            ->with(CompleteTaskCommand::class);

        $this->mapping
            ->method('getMethodName')
            ->willReturn('someMethodThatDoesNotExist');

        $this->expectException(CanNotInvokeHandler::class);
        $this->middleware->execute($command, $this->mockNext());
    }

    public function testDynamicMethodNamesAreSupported(): void
    {
        $command = new CompleteTaskCommand();
        $handler = new DynamicMethodsHandler();

        $this->container
            ->method('get')
            ->willReturn($handler);

        $this->mapping
            ->method('getClassName')
            ->with(CompleteTaskCommand::class);

        $this->mapping
            ->method('getMethodName')
            ->willReturn('someHandlerMethod');

        $this->middleware->execute($command, $this->mockNext());

        self::assertEquals(
            ['someHandlerMethod'],
            $handler->getMethodsInvoked()
        );
    }

    protected function mockNext(): callable
    {
        return static function (): void {
            throw new LogicException('Middleware fell through to next callable, this should not happen in the test.');
        };
    }
}
