<?php

namespace Olivermbs\Enumshare\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Label
{
    public function __construct(
        public readonly string $text
    ) {}
}
