<?php

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Tests\TestCase;

enum PruneKeptEnum: string
{
    case A = 'a';
}

class PruneTest extends TestCase
{
    protected string $out;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('enumshare.enums', [PruneKeptEnum::class]);
        config()->set('enumshare.auto_discovery', false);
        $this->out = sys_get_temp_dir().'/enumshare-prune-'.getmypid();
        File::deleteDirectory($this->out);
        File::makeDirectory($this->out, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->out);
        parent::tearDown();
    }

    public function test_prune_deletes_orphaned_generated_files(): void
    {
        File::put("{$this->out}/OldEnum.ts", "/* eslint-disable */\n// Auto-generated from App\\Enums\\OldEnum\nexport const OldEnum = {};\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])
            ->expectsOutputToContain('OldEnum.ts')
            ->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->out}/OldEnum.ts");
        $this->assertFileExists("{$this->out}/PruneKeptEnum.ts");
    }

    public function test_prune_never_deletes_unmarked_files(): void
    {
        File::put("{$this->out}/handwritten.ts", "export const mine = 1;\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])->assertSuccessful();

        $this->assertFileExists("{$this->out}/handwritten.ts");
    }

    public function test_prune_removes_stale_index_when_index_disabled(): void
    {
        File::put("{$this->out}/index.ts", "// Auto-generated. Do not edit.\n\nexport { OldEnum } from './OldEnum';\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->out}/index.ts");
    }

    public function test_prune_keeps_index_when_index_enabled(): void
    {
        config()->set('enumshare.index', true);

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])->assertSuccessful();

        $this->assertFileExists("{$this->out}/index.ts");
    }

    public function test_prune_keeps_current_files_with_trailing_slash_path(): void
    {
        $this->artisan('enums:export', ['--path' => $this->out.'/', '--prune' => true])->assertSuccessful();

        $this->assertFileExists("{$this->out}/PruneKeptEnum.ts");
    }

    public function test_prune_deletes_orphans_when_manifest_is_empty(): void
    {
        config()->set('enumshare.enums', []);
        File::put("{$this->out}/OldEnum.ts", "// Auto-generated from App\\Enums\\OldEnum\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])
            ->expectsOutput('No enums found to export.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->out}/OldEnum.ts");
    }
}
