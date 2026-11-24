<?php

namespace App\Services\Firebase;

use RuntimeException;

class FirebaseException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}
