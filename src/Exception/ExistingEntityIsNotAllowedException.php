<?php

namespace App\Exception;

class ExistingEntityIsNotAllowedException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf("It is not allowed to modify an existing %s", $message);
        parent::__construct($message, $code, $previous);
    }

}
