<?php

namespace Olivermbs\Enumshare\Tests\Fixtures;

use Olivermbs\Enumshare\Attributes\Label;
use Olivermbs\Enumshare\Attributes\Meta;

enum TestEnum: string
{
    #[Label('Active Status')]
    #[Meta(['color' => 'green', 'icon' => 'check'])]
    case Active = 'active';

    #[Label('Inactive Status')]
    #[Meta(['color' => 'red', 'icon' => 'x'])]
    case Inactive = 'inactive';
}
