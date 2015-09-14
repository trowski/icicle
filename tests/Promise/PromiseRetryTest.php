<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Tests\TestCase;

class PromiseRetryTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testPromisorReturningScalar()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Promise\resolve($value);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Promise\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorReturnsFulfilledPromise()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Promise\resolve($value);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Promise\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorReturnsPendingPromise()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Promise\resolve($value)->delay(self::TIMEOUT);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Promise\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorThrowsException()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            throw $exception;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\retry($promisor, $this->createCallback(0))
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testPromiseRejectingCallsOnRejected()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            return Promise\reject($exception);
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testPendingPromiseRejectingCallsOnRejected()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            $promise = new Promise\Promise(function () {});
            return $promise->timeout(self::TIMEOUT, $exception);
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testOnRejectedThrowingRejectsPromise()
    {
        $exception = new Exception();
        
        $promisor = function () {
            return Promise\reject();
        };
        
        $onRejected = function () use ($exception) {
            throw $exception;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testOnRejectedReturningTrueCallsPromisor()
    {
        $value = 'testing';
        $exception = new Exception();
        
        $promisor = function () use ($value, $exception) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Promise\reject($exception);
            }
            
            return Promise\resolve($value);
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return true;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Promise\retry($promisor, $onRejected)
            ->done($callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testPromisorThrowingOnSubsequentCallRejectsPromise()
    {
        $exception1 = new Exception();
        $exception2 = new Exception();
        
        $promisor = function () use ($exception1, $exception2) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Promise\reject($exception1);
            }
            
            throw $exception2;
        };
        
        $onRejected = function ($value) use ($exception1) {
            $this->assertSame($exception1, $value);
            return true;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception2));
        
        Promise\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }

    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testVoidCallbackDoesNotCauseRetry()
    {
        $exception = new Exception();

        $promisor = function () use ($exception) {
            return Promise\reject($exception);
        };

        $onRejected = function () {};

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        Promise\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testInitialPromiseCancelledOnCancellation()
    {
        $delay = 0.1;

        $exception = new Exception();

        $promise = Promise\resolve()->delay($delay * 2);

        $promisor = function () use ($promise) {
            return $promise;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise->done($this->createCallback(0), $callback);

        $promise = Promise\retry($promisor, $this->createCallback(0));
        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }

    /**
     * @depends testInitialPromiseCancelledOnCancellation
     */
    public function testPromiseCancelledOnCancellationAfterRejection()
    {
        $delay = 0.1;

        $exception = new Exception();
        $reason = new Exception();

        $promise = Promise\resolve()->delay($delay * 2);

        $promisor = function () use ($promise, $reason) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Promise\reject($reason);
            } else {
                return $promise;
            }
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($reason));

        $onRejected = function (Exception $exception) use ($callback) {
            $callback($exception);
            return true;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise->done($this->createCallback(0), $callback);

        $promise = Promise\retry($promisor, $onRejected);
        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }
}
