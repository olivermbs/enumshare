<?php

namespace Olivermbs\Enumshare\Tests\IndexExport;

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Tests\TestCase;

enum index: string
{
    case Active = 'active';
}

class IndexExportTest extends TestCase
{
    protected string $out;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('enumshare.enums', []);
        config()->set('enumshare.auto_discovery', false);
        $this->out = sys_get_temp_dir().'/enumshare-index-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->out);
        parent::tearDown();
    }

    public function test_empty_export_creates_a_barrel_that_passes_check(): void
    {
        $options = ['--path' => $this->out, '--index' => true];
        $this->artisan('enums:export', $options)->assertSuccessful();
        $this->assertStringContainsString('export {};', File::get($this->out.'/index.ts'));
        $this->artisan('enums:export', [...$options, '--check' => true])->assertSuccessful();
    }

    public function test_empty_export_with_prune_updates_and_keeps_the_barrel(): void
    {
        config()->set('enumshare.index', true);
        File::makeDirectory($this->out);
        File::put($this->out.'/Old.ts', "// Auto-generated from Old\n");
        File::put($this->out.'/index.ts', "// Auto-generated. Do not edit.\nexport { Old } from './Old';\n");

        $this->artisan('enums:export', ['--path' => $this->out, '--prune' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($this->out.'/Old.ts');
        $this->assertStringNotContainsString('Old', File::get($this->out.'/index.ts'));
        $this->artisan('enums:export', ['--path' => $this->out, '--check' => true])->assertSuccessful();
    }

    public function test_index_collision_fails_before_writing_or_pruning(): void
    {
        config()->set('enumshare.enums', [index::class]);
        File::makeDirectory($this->out);
        File::put($this->out.'/index.ts', 'existing content');
        File::put($this->out.'/Old.ts', "// Auto-generated from Old\n");

        foreach ([false, true] as $check) {
            $this->artisan('enums:export', [
                '--path' => $this->out,
                '--index' => true,
                '--prune' => true,
                '--check' => $check,
            ])->expectsOutputToContain('conflicts with the generated index.ts')->assertFailed();
        }

        $this->assertSame('existing content', File::get($this->out.'/index.ts'));
        $this->assertFileExists($this->out.'/Old.ts');
    }

    public function test_index_enum_can_export_without_a_barrel(): void
    {
        config()->set('enumshare.enums', [index::class]);
        $this->artisan('enums:export', ['--path' => $this->out])->assertSuccessful();
        $this->assertStringContainsString('export const index', File::get($this->out.'/index.ts'));
    }
}
