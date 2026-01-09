<?php

namespace Olivermbs\Enumshare\Exceptions;

use Exception;

class InvalidEnumException extends Exception
{
    public static function classDoesNotExist(string $enumClass): self
    {
        return new self("Enum class '{$enumClass}' does not exist.");
    }

    public static function notAnEnum(string $enumClass): self
    {
        return new self("Class '{$enumClass}' is not an enum.");
    }

    public static function noCases(string $enumClass): self
    {
        return new self("Enum '{$enumClass}' has no cases to export.");
    }

    public static function duplicateShortName(string $shortName, string $firstClass, string $secondClass): self
    {
        return new self(
            "Enum name collision: '{$shortName}' is used by both {$firstClass} and {$secondClass}. ".
            'Use unique enum names or configure specific enums in config/enumshare.php.'
        );
    }

    public static function reflectionError(string $enumClass, string $error): self
    {
        return new self("Failed to reflect enum class '{$enumClass}': {$error}");
    }
}
