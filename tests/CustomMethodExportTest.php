<?php

namespace Tests;

use Illuminate\Support\Facades\Log;
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

enum ReservedExportMethodName: string
{
    case Active = 'active';

    #[ExportMethod('label')]
    public function customLabel(): string
    {
        return 'Overridden';
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

it('skips and warns about custom methods using reserved entry keys', function () {
    Log::partialMock()
        ->shouldReceive('warning')
        ->once()
        ->with('Enumshare export method uses a reserved entry key', [
            'enum' => ReservedExportMethodName::class,
            'method' => 'customLabel',
            'key' => 'label',
        ]);

    $result = (new EnumExtractor)->extract(ReservedExportMethodName::class);

    expect($result['entries'][0]['label'])->toBe('Active');
});
