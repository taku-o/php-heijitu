<?php

declare(strict_types=1);

namespace Heijitu\Tests\Exception;

use Heijitu\Exception\ConfigException;
use Heijitu\Exception\HeijituException;
use Heijitu\Exception\ProviderException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    /** @return array<string, array<string>> */
    public function exceptionClassProvider(): array
    {
        return [
            'ConfigException'   => [ConfigException::class],
            'ProviderException' => [ProviderException::class],
        ];
    }

    /**
     * @dataProvider exceptionClassProvider
     * @param string $exceptionClass
     */
    public function testIsCaughtAsHeijituException(string $exceptionClass): void
    {
        $caught = false;
        try {
            throw new $exceptionClass('error');
        } catch (HeijituException $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
    }

    /**
     * @dataProvider exceptionClassProvider
     * @param string $exceptionClass
     */
    public function testIsRuntimeException(string $exceptionClass): void
    {
        $exception = new $exceptionClass('error');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    /**
     * @dataProvider exceptionClassProvider
     * @param string $exceptionClass
     */
    public function testPreservesMessage(string $exceptionClass): void
    {
        $message = 'test error message';
        $exception = new $exceptionClass($message);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @dataProvider exceptionClassProvider
     * @param string $exceptionClass
     */
    public function testPreservesPreviousException(string $exceptionClass): void
    {
        $previous = new \RuntimeException('original error');
        $exception = new $exceptionClass('wrapped', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
