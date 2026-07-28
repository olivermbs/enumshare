<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Support\EnumExporter;
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

    public function test_enums_export_reports_files_skipped_because_content_is_unchanged(): void
    {
        $this->createTestEnumFile();
        $tempDir = sys_get_temp_dir().'/enumshare-export-test-'.uniqid();

        $this->artisan('enums:export', ['--path' => $tempDir])->assertSuccessful();

        $this->artisan('enums:export', ['--path' => $tempDir])
            ->expectsOutput('Skipped 1 enum(s) (content unchanged).')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_enums_export_catches_throwables(): void
    {
        $exporter = Mockery::mock(EnumExporter::class);
        $exporter->shouldReceive('export')->once()->andThrow(new Error('Unexpected failure'));
        $this->app->instance(EnumExporter::class, $exporter);

        $this->artisan('enums:export')
            ->expectsOutput('Export failed: Unexpected failure')
            ->assertFailed();
    }

    public function test_index_config_option_generates_barrel_file(): void
    {
        $this->createTestEnumFile();
        config()->set('enumshare.index', true);

        $tempDir = sys_get_temp_dir().'/enumshare-index-config-'.time();

        $this->artisan('enums:export', ['--path' => $tempDir])->assertSuccessful();

        $this->assertFileExists("{$tempDir}/index.ts");

        File::deleteDirectory($tempDir);
    }

    public function test_about_command_shows_enumshare_section(): void
    {
        $this->createTestEnumFile();

        $this->assertSame(
            0,
            Artisan::call('about', ['--json' => true])
        );

        $output = json_decode(
            Artisan::output(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('enumshare', $output);
        $this->assertSame('full', $output['enumshare']['mode']);
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
