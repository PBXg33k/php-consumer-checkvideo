<?php

namespace App\Message;

/**
 * Empty message for the sole purpose of keeping connection to amqp a live for chaining messages
 * after video processing, which takes up to an hour per job.
 *
 * Class FooMessage
 */
class FooMessage
{
}
