<?php

namespace Olivermbs\Enumshare\Exceptions;

use Exception;
use Throwable;

class ExportException extends Exception
{
    public static function directoryCreationFailed(string $directory, ?Throwable $previous = null): self
    {
        return new self("Failed to create directory: {$directory}", 0, $previous);
    }
}
