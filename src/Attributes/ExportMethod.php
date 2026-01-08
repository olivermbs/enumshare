<?php

namespace Olivermbs\Enumshare\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ExportMethod
{
    public function __construct(
        public ?string $name = null
    ) {}
}
