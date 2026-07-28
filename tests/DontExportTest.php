<?php

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Attributes\DontExport;
use Olivermbs\Enumshare\Support\EnumRegistry;
use Olivermbs\Enumshare\Tests\TestCase;

#[DontExport]
enum HiddenTestEnum: string
{
    case Secret = 'secret';
}

enum VisibleTestEnum: string
{
    case Open = 'open';
}

class DontExportTest extends TestCase
{
    public function test_marked_enum_is_excluded_from_manifest(): void
    {
        $registry = new EnumRegistry([HiddenTestEnum::class, VisibleTestEnum::class]);

        $manifest = $registry->manifest();

        $this->assertArrayHasKey('VisibleTestEnum', $manifest);
        $this->assertArrayNotHasKey('HiddenTestEnum', $manifest);
    }

    public function test_marked_enum_is_excluded_from_exportable_classes(): void
    {
        $registry = new EnumRegistry([HiddenTestEnum::class, VisibleTestEnum::class]);

        $this->assertSame([VisibleTestEnum::class], $registry->exportableEnumClasses());
    }

    public function test_configured_marked_enum_emits_warning(): void
    {
        config()->set('enumshare.enums', [HiddenTestEnum::class]);
        config()->set('enumshare.auto_discovery', false);

        $this->artisan('enums:export', ['--path' => base_path('test_enums_out')])
            ->expectsOutputToContain('Enum HiddenTestEnum is listed in config but marked #[DontExport]; skipping.')
            ->assertSuccessful();

        File::deleteDirectory(base_path('test_enums_out'));
    }
}
