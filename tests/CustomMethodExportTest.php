<?php

namespace Tests;

use Olivermbs\Enumshare\Attributes\ExportMethod;
use Olivermbs\Enumshare\Support\EnumExtractor;

enum TestContactType: int
{
    case EMAIL = 1;
    case PHONE = 2;
    case SMS = 3;
    case POSTAL = 4;

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Phone',
            self::SMS => 'SMS',
            self::POSTAL => 'Postal Mail',
        };
    }

    #[ExportMethod]
    public function isInstant(): bool
    {
        return $this !== self::POSTAL;
    }

    #[ExportMethod('requiresPhoneNumber')]
    public function needsPhone(): bool
    {
        return match ($this) {
            self::PHONE, self::SMS => true,
            default => false,
        };
    }
}

it('exports custom method results as properties', function () {
    $extractor = new EnumExtractor;
    $result = $extractor->extract(TestContactType::class);

    expect($result)->toHaveKey('entries');
    expect($result['entries'])->toHaveCount(4);

    $postalEntry = collect($result['entries'])->firstWhere('key', 'POSTAL');
    $emailEntry = collect($result['entries'])->firstWhere('key', 'EMAIL');
    $phoneEntry = collect($result['entries'])->firstWhere('key', 'PHONE');

    // Check isInstant property
    expect($postalEntry)->toHaveKey('isInstant');
    expect($postalEntry['isInstant'])->toBeFalse();
    expect($emailEntry['isInstant'])->toBeTrue();

    // Check custom named property
    expect($phoneEntry)->toHaveKey('requiresPhoneNumber');
    expect($phoneEntry['requiresPhoneNumber'])->toBeTrue();
    expect($emailEntry['requiresPhoneNumber'])->toBeFalse();
});
