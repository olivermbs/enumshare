<?php

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Tests\TestCase;

class EnumCommandsTest extends TestCase
{
    protected string $testEnumsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testEnumsPath = base_path('test_enums');

        if (File::isDirectory($this->testEnumsPath)) {
            File::deleteDirectory($this->testEnumsPath);
        }

        File::makeDirectory($this->testEnumsPath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testEnumsPath)) {
            File::deleteDirectory($this->testEnumsPath);
        }

        parent::tearDown();
    }

    public function test_enums_export_list_shows_enums(): void
    {
        $this->createTestEnumFile();

        $this->artisan('enums:export --list')
            ->expectsOutput('Enums to export:')
            ->expectsOutputToContain('CommandTestEnum')
            ->assertSuccessful();
    }

    public function test_enums_export_list_warns_when_empty(): void
    {
        $this->artisan('enums:export --list')
            ->expectsOutput('No enums found to export.')
            ->assertSuccessful();
    }

    public function test_enums_export_works_with_autodiscovery(): void
    {
        $this->createTestEnumFile();

        $tempDir = sys_get_temp_dir().'/enumshare-export-test-'.time();

        $this->artisan('enums:export', [
            '--path' => $tempDir,
        ])
            ->expectsOutputToContain('Exported 1 enum(s) to:')
            ->assertSuccessful();

        // Check individual enum file is created
        expect(File::exists($tempDir.'/CommandTestEnum.ts'))->toBeTrue();

        $enumContent = File::get($tempDir.'/CommandTestEnum.ts');
        expect($enumContent)
            ->toContain('export const CommandTestEnum')
            ->toContain('keys: KEYS,')
            ->toContain('values: VALUES,')
            ->toContain('from(');

        // Clean up
        File::deleteDirectory($tempDir);
    }

    protected function createTestEnumFile(): void
    {
        $directory = $this->testEnumsPath.'/App/Enums';
        File::makeDirectory($directory, 0755, true);

        $content = "<?php

namespace App\\Enums;

enum CommandTestEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}";

        File::put($directory.'/CommandTestEnum.php', $content);
        require_once $directory.'/CommandTestEnum.php';
    }

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('enumshare.auto_discovery', true);
        $app['config']->set('enumshare.auto_paths', ['test_enums']);
    }
}
