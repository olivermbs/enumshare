<?php

namespace Olivermbs\Enumshare\Tests\Fixtures;

use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Attributes\TranslatedLabel;
use Olivermbs\Enumshare\Concerns\SharesWithFrontend;

enum MultilingualTestEnum: string
{
    use SharesWithFrontend;

    #[TranslatedLabel('status.active')]
    #[Meta(['color' => 'green', 'icon' => 'check'])]
    case Active = 'active';

    #[TranslatedLabel('status.inactive')]
    #[Meta(['color' => 'red', 'icon' => 'x'])]
    case Inactive = 'inactive';
}
