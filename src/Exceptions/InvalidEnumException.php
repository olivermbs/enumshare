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

    public static function missingTrait(string $enumClass): self
    {
        return new self(
            "Enum '{$enumClass}' must use the SharesWithFrontend trait to be exported."
        );
    }

    public static function noCases(string $enumClass): self
    {
        return new self("Enum '{$enumClass}' has no cases to export.");
    }

    public static function reflectionError(string $enumClass, string $error): self
    {
        return new self("Failed to reflect enum class '{$enumClass}': {$error}");
    }
}
