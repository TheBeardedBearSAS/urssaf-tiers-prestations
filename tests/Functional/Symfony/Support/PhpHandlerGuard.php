<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use Closure;
use Throwable;

/**
 * FrameworkBundle registers PHP error and exception handlers when it boots, and Symfony 6.4 does not restore them on
 * shutdown (PHPUnit then reports the test as risky). The guard puts a sentinel handler first and, once released, pops
 * every handler registered above it. Guards may overlap (a test booting a second application before the first one is
 * destroyed): a guard released while a later one is still open is unwound when the later one is released.
 */
final class PhpHandlerGuard
{
    private const MAX_HANDLERS = 10;

    /** @var list<self> */
    private static array $open = [];

    private bool $released = false;

    /**
     * @param Closure(int, string): bool $errorSentinel
     * @param Closure(Throwable): void   $exceptionSentinel
     */
    private function __construct(
        private readonly Closure $errorSentinel,
        private readonly Closure $exceptionSentinel,
    ) {}

    public static function install(): self
    {
        $errors = static fn(int $severity, string $message): bool => $severity < 0 && $message === '';
        $exceptions = static function (Throwable $failure): void {
            $failure->getPrevious();
        };
        set_error_handler($errors);
        set_exception_handler($exceptions);

        $guard = new self($errors, $exceptions);
        self::$open[] = $guard;

        return $guard;
    }

    public function release(): void
    {
        $this->released = true;
        while (self::$open !== [] && self::$open[array_key_last(self::$open)]->released) {
            array_pop(self::$open)->unwind();
        }
    }

    private function unwind(): void
    {
        for ($i = 0; $i < self::MAX_HANDLERS && self::currentErrorHandler() !== $this->errorSentinel; ++$i) {
            restore_error_handler();
        }
        restore_error_handler();
        for ($i = 0; $i < self::MAX_HANDLERS && self::currentExceptionHandler() !== $this->exceptionSentinel; ++$i) {
            restore_exception_handler();
        }
        restore_exception_handler();
    }

    /**
     * @return (callable(int, string, string, int): bool)|null
     */
    private static function currentErrorHandler(): ?callable
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        // @mago-expect analysis:less-specific-nested-return-statement The stubs of set_error_handler() declare a loose callable.
        return $handler;
    }

    /**
     * @return (callable(Throwable): void)|null
     */
    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
