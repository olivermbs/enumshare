<?php

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Tests\TestCase;

enum CheckTestEnum: string
{
    case A = 'a';
}

class CheckTest extends TestCase
{
    protected string $out;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('enumshare.enums', [CheckTestEnum::class]);
        config()->set('enumshare.auto_discovery', false);
        $this->out = sys_get_temp_dir().'/enumshare-check-'.getmypid();
        File::deleteDirectory($this->out);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->out);
        parent::tearDown();
    }

    public function test_check_passes_when_up_to_date(): void
    {
        $this->artisan('enums:export', ['--path' => $this->out])->assertSuccessful();

        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();
    }

    public function test_check_fails_on_stale_file(): void
    {
        $this->artisan('enums:export', ['--path' => $this->out])->assertSuccessful();
        File::put("{$this->out}/CheckTestEnum.ts", "// Auto-generated from stale\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])
            ->expectsOutputToContain('CheckTestEnum.ts')
            ->assertFailed();

        $this->assertStringContainsString('stale', File::get("{$this->out}/CheckTestEnum.ts"));
    }

    public function test_check_fails_on_missing_file_and_writes_nothing(): void
    {
        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->out);
    }

    public function test_check_reports_orphans_without_failing_or_deleting(): void
    {
        $this->artisan('enums:export', ['--path' => $this->out])->assertSuccessful();
        File::put("{$this->out}/OldEnum.ts", "// Auto-generated from App\\Enums\\OldEnum\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])
            ->expectsOutputToContain('OldEnum.ts')
            ->assertSuccessful();

        $this->assertFileExists("{$this->out}/OldEnum.ts");
    }

    public function test_check_includes_index_when_enabled(): void
    {
        config()->set('enumshare.index', true);
        $this->artisan('enums:export', ['--path' => $this->out])->assertSuccessful();
        File::delete("{$this->out}/index.ts");

        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])
            ->expectsOutputToContain('index.ts')
            ->assertFailed();
    }
}
