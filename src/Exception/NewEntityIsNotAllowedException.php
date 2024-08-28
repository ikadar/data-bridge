<?php

namespace App\Exception;

class NewEntityIsNotAllowedException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf("It is not allowed to create a new %s", $message);
        parent::__construct($message, $code, $previous);
    }

}
