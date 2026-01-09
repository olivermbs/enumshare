<?php

use Olivermbs\Enumshare\Support\EnumExtractor;

beforeEach(function () {
    $this->extractor = new EnumExtractor;
});

it('extracts enum without trait', function () {
    // Create a plain enum without any trait
    if (! enum_exists('PlainTestEnum')) {
        eval('enum PlainTestEnum: int { case First = 1; case Second = 2; }');
    }

    $result = $this->extractor->extract('PlainTestEnum');

    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('fqcn');
    expect($result)->toHaveKey('backingType');
    expect($result)->toHaveKey('entries');
    expect($result)->toHaveKey('options');

    expect($result['name'])->toBe('PlainTestEnum');
    expect($result['backingType'])->toBe('int');
    expect($result['entries'])->toHaveCount(2);

    expect($result['entries'][0]['key'])->toBe('First');
    expect($result['entries'][0]['value'])->toBe(1);
    expect($result['entries'][1]['key'])->toBe('Second');
    expect($result['entries'][1]['value'])->toBe(2);
});

it('extracts enum with attributes', function () {
    require_once __DIR__.'/../Fixtures/TestEnum.php';

    $result = $this->extractor->extract('Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum');

    expect($result['name'])->toBe('TestEnum');
    expect($result['backingType'])->toBe('string');
    expect($result['entries'])->toHaveCount(2);

    // Check labels from #[Label] attribute
    expect($result['entries'][0]['label'])->toBe('Active Status');
    expect($result['entries'][1]['label'])->toBe('Inactive Status');

    // Check meta from #[Meta] attribute
    expect($result['entries'][0]['meta'])->toHaveKey('color');
    expect($result['entries'][0]['meta']['color'])->toBe('green');
});

it('uses case name as label fallback for plain enums', function () {
    if (! enum_exists('PlainLabelTestEnum')) {
        eval('enum PlainLabelTestEnum: string { case SomeCase = "some"; }');
    }

    $result = $this->extractor->extract('PlainLabelTestEnum');

    // Without #[Label] attribute, should fall back to case name
    expect($result['entries'][0]['label'])->toBe('SomeCase');
});

it('extracts string backed enum correctly', function () {
    if (! enum_exists('StringBackedTestEnum')) {
        eval('enum StringBackedTestEnum: string { case Active = "active"; case Inactive = "inactive"; }');
    }

    $result = $this->extractor->extract('StringBackedTestEnum');

    expect($result['backingType'])->toBe('string');
    expect($result['entries'][0]['value'])->toBe('active');
});

it('generates options array for select dropdowns', function () {
    if (! enum_exists('OptionsTestEnum')) {
        eval('enum OptionsTestEnum: int { case One = 1; case Two = 2; }');
    }

    $result = $this->extractor->extract('OptionsTestEnum');

    expect($result['options'])->toHaveCount(2);
    expect($result['options'][0])->toHaveKey('value');
    expect($result['options'][0])->toHaveKey('label');
    expect($result['options'][0]['value'])->toBe(1);
});
