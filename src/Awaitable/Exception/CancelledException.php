<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Exception;

class CancelledException extends ReasonException
{
    public function __construct($reason)
    {
        parent::__construct($reason, 'Promise cancelled.');
    }
}