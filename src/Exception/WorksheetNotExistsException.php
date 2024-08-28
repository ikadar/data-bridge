<?php

namespace App\Exception;

class WorksheetNotExistsException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf("Worksheet %s doesn't exist", $message);
        parent::__construct($message, $code, $previous);
    }

}
