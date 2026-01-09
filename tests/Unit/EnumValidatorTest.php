<?php

use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use Olivermbs\Enumshare\Support\EnumValidator;

require_once __DIR__.'/../Fixtures/TestEnum.php';

beforeEach(function () {
    $this->validator = new EnumValidator;
});

it('validates a valid enum successfully', function () {
    expect($this->validator->isValidEnumForExport('Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum'))->toBeTrue();

    // Should not throw exception
    $this->validator->validateEnumForExport('Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum');
    expect(true)->toBeTrue(); // Test passes if no exception thrown
});

it('rejects non-existent class', function () {
    expect($this->validator->isValidEnumForExport('NonExistentClass'))->toBeFalse();

    expect(fn () => $this->validator->validateEnumForExport('NonExistentClass'))
        ->toThrow(InvalidEnumException::class, 'does not exist');
});

it('rejects non-enum classes', function () {
    expect($this->validator->isValidEnumForExport('stdClass'))->toBeFalse();

    expect(fn () => $this->validator->validateEnumForExport('stdClass'))
        ->toThrow(InvalidEnumException::class, 'is not an enum');
});

it('validates any PHP enum', function () {
    // Create a plain enum
    if (! enum_exists('TestPlainEnum')) {
        eval('enum TestPlainEnum: string { case Test = "test"; }');
    }

    // Any PHP enum can be exported
    expect($this->validator->isValidEnumForExport('TestPlainEnum'))->toBeTrue();

    // Should not throw exception
    expect(fn () => $this->validator->validateEnumForExport('TestPlainEnum'))
        ->not->toThrow(\Exception::class);
});

it('validates multiple enums and returns results', function () {
    $enums = [
        'Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum',                // Valid
        'NonExistentClass',          // Invalid - doesn't exist
        'stdClass',                  // Invalid - not an enum
    ];

    $result = $this->validator->validateMultipleEnumsForExport($enums);

    expect($result)->toHaveKey('valid');
    expect($result)->toHaveKey('errors');

    expect($result['valid'])->toContain('Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum');
    expect($result['valid'])->toHaveCount(1);

    expect($result['errors'])->toHaveKey('NonExistentClass');
    expect($result['errors'])->toHaveKey('stdClass');
    expect($result['errors'])->toHaveCount(2);

    expect($result['errors']['NonExistentClass'])->toContain('does not exist');
    expect($result['errors']['stdClass'])->toContain('is not an enum');
});

it('handles empty enum validation', function () {
    $result = $this->validator->validateMultipleEnumsForExport([]);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toBeEmpty();
});

it('handles all valid enums', function () {
    $enums = ['Olivermbs\\Enumshare\\Tests\\Fixtures\\TestEnum'];

    $result = $this->validator->validateMultipleEnumsForExport($enums);

    expect($result['valid'])->toEqual($enums);
    expect($result['errors'])->toBeEmpty();
});

it('handles all invalid enums', function () {
    $enums = ['NonExistentClass', 'stdClass'];

    $result = $this->validator->validateMultipleEnumsForExport($enums);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(2);
    expect($result['errors'])->toHaveKeys($enums);
});
