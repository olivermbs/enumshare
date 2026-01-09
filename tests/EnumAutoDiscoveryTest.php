<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Support\EnumAutoDiscovery;
use Olivermbs\Enumshare\Support\EnumRegistry;
use Olivermbs\Enumshare\Tests\TestCase;

class EnumAutoDiscoveryTest extends TestCase
{
    protected string $testEnumsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Use array cache for testing
        config(['cache.default' => 'array']);

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

        // Use array cache for testing instead of database cache
        config(['cache.default' => 'array']);

        parent::tearDown();
    }

    public function test_discovers_enums_from_configured_paths(): void
    {
        $this->createTestEnumFile('TestStatus', 'App\\TestEnums');

        $discovery = new EnumAutoDiscovery(['test_enums']);
        $discoveredEnums = $discovery->discover();

        expect($discoveredEnums)->toContain('App\\TestEnums\\TestStatus');
    }

    public function test_discovers_all_valid_enums_in_paths(): void
    {
        $this->createTestEnumFile('FirstEnum', 'App\\Enums');
        $this->createTestEnumFile('SecondEnum', 'App\\Models');

        $discovery = new EnumAutoDiscovery(['test_enums']);
        $discoveredEnums = $discovery->discover();

        expect($discoveredEnums)
            ->toContain('App\\Enums\\FirstEnum')
            ->and($discoveredEnums)->toContain('App\\Models\\SecondEnum');
    }

    public function test_always_discovers_fresh_enums(): void
    {
        $this->createTestEnumFile('FreshEnum', 'App\\Enums');

        $discovery = new EnumAutoDiscovery(['test_enums']);

        // First call
        $firstCall = $discovery->discover();
        expect($firstCall)->toContain('App\\Enums\\FreshEnum');

        // Add another enum
        $this->createTestEnumFile('AnotherFreshEnum', 'App\\Enums');

        // Second call should include the new enum (no caching)
        $secondCall = $discovery->discover();
        expect($secondCall)->toContain('App\\Enums\\FreshEnum')
            ->and($secondCall)->toContain('App\\Enums\\AnotherFreshEnum');
    }

    public function test_skips_non_enum_classes(): void
    {
        // Create a class that's not an enum - should be skipped
        $this->createTestClassFile('NotAnEnum', 'App\\Enums');
        // Create an enum - should be discovered
        $this->createTestEnumFile('PlainEnum', 'App\\Enums');

        $discovery = new EnumAutoDiscovery(['test_enums']);
        $discoveredEnums = $discovery->discover();

        // Non-enum classes should be skipped
        expect($discoveredEnums)->not->toContain('App\\Enums\\NotAnEnum');
        // Plain enums should be discovered
        expect($discoveredEnums)->toContain('App\\Enums\\PlainEnum');
    }

    public function test_enum_registry_integrates_with_autodiscovery(): void
    {
        $this->createTestEnumFile('RegistryTestEnum', 'App\\Enums');

        $discovery = new EnumAutoDiscovery(['test_enums']);
        $registry = new EnumRegistry([], $discovery);

        // Enable autodiscovery in config
        config(['enumshare.auto_discovery' => true]);

        $manifest = $registry->manifest();

        expect($manifest)->toHaveKey('RegistryTestEnum');
    }

    public function test_combines_configured_and_discovered_enums(): void
    {
        // Create a discovered enum
        $this->createTestEnumFile('DiscoveredEnum', 'App\\Enums');

        // Create a configured enum (defined in test)
        $configuredEnums = [\TestConfiguredEnum::class];

        $discovery = new EnumAutoDiscovery(['test_enums']);
        $registry = new EnumRegistry($configuredEnums, $discovery);

        config(['enumshare.auto_discovery' => true]);

        $manifest = $registry->manifest();

        expect($manifest)
            ->toHaveKey('DiscoveredEnum')
            ->toHaveKey('TestConfiguredEnum');
    }

    protected function createTestEnumFile(string $enumName, string $namespace): void
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $directory = $this->testEnumsPath.'/'.dirname($namespacePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = "<?php

namespace {$namespace};

use Olivermbs\\Enumshare\\Attributes\\Label;

enum {$enumName}: string
{
    #[Label('Test Case')]
    case TestCase = 'test';
}";

        File::put($this->testEnumsPath.'/'.$enumName.'.php', $content);

        // Include the file so the class exists for testing
        require_once $this->testEnumsPath.'/'.$enumName.'.php';
    }

    protected function createTestClassFile(string $className, string $namespace): void
    {
        $content = "<?php

namespace {$namespace};

class {$className}
{
    // Not an enum
}";

        File::put($this->testEnumsPath.'/'.$className.'.php', $content);
    }

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('enumshare.autodiscovery', [
            'enabled' => true,
            'paths' => ['test_enums'],
            'namespaces' => ['App\\Enums\\*'],
            'cache' => [
                'enabled' => false,
                'key' => 'test.discovered_enums',
                'ttl' => 3600,
            ],
        ]);
    }
}

// Test enum for configured enums testing
enum TestConfiguredEnum: string
{
    case Configured = 'configured';
}
