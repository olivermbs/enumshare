<?php

namespace Olivermbs\Enumshare\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class TranslatedLabel
{
    public function __construct(
        public readonly string $key,
        public readonly array $parameters = []
    ) {}
}
