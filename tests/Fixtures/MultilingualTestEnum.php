<?php

namespace Olivermbs\Enumshare\Tests\Fixtures;

use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Attributes\TranslatedLabel;

enum MultilingualTestEnum: string
{
    #[TranslatedLabel('status.active')]
    #[Meta(['color' => 'green', 'icon' => 'check'])]
    case Active = 'active';

    #[TranslatedLabel('status.inactive')]
    #[Meta(['color' => 'red', 'icon' => 'x'])]
    case Inactive = 'inactive';
}
