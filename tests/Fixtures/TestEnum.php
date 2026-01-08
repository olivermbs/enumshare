<?php

namespace Olivermbs\Enumshare\Tests\Fixtures;

use Olivermbs\Enumshare\Attributes\Label;
use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Concerns\SharesWithFrontend;

enum TestEnum: string
{
    use SharesWithFrontend;

    #[Label('Active Status')]
    #[Meta(['color' => 'green', 'icon' => 'check'])]
    case Active = 'active';

    #[Label('Inactive Status')]
    #[Meta(['color' => 'red', 'icon' => 'x'])]
    case Inactive = 'inactive';
}
