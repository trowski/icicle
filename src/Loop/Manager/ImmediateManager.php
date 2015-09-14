<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\ImmediateInterface;
use Icicle\Loop\LoopInterface;
use Icicle\Loop\Structures\ObjectStorage;

class ImmediateManager implements ImmediateManagerInterface
{
    /**
     * @var \Icicle\Loop\LoopInterface
     */
    private $loop;

    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \SplQueue
     */
    private $queue;
    
    /**
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $immediates;
    
    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        $this->queue = new \SplQueue();
        $this->immediates = new ObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(callable $callback, array $args = [])
    {
        $immediate = $this->factory->immediate($this, $callback, $args);
        
        $this->execute($immediate);
        
        return $immediate;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(ImmediateInterface $immediate)
    {
        return $this->immediates->contains($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ImmediateInterface $immediate)
    {
        if (!$this->immediates->contains($immediate)) {
            $this->queue->push($immediate);
            $this->immediates->attach($immediate);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(ImmediateInterface $immediate)
    {
        if ($this->immediates->contains($immediate)) {
            $this->immediates->detach($immediate);

            foreach ($this->queue as $key => $event) {
                if ($event === $immediate) {
                    unset($this->queue[$key]);
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return !$this->immediates->count();
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(ImmediateInterface $immediate)
    {
        $this->immediates->unreference($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function reference(ImmediateInterface $immediate)
    {
        $this->immediates->reference($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = new \SplQueue();
        $this->immediates = new \SplObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        if (!$this->queue->isEmpty()) {
            $immediate = $this->queue->shift();

            $this->immediates->detach($immediate);

            // Execute the immediate.
            $immediate->call();

            return true;
        }

        return false;
    }
}
