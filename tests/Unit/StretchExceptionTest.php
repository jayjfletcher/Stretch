<?php

declare(strict_types=1);

use JayI\Stretch\Exceptions\StretchException;

it('extends base Exception', function () {
    $exception = new StretchException('Test error');

    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toBe('Test error');
});

it('preserves the original exception as previous', function () {
    $original = new RuntimeException('Original error');
    $exception = new StretchException('Wrapped error', 0, $original);

    expect($exception->getPrevious())->toBe($original);
    expect($exception->getPrevious()->getMessage())->toBe('Original error');
});

it('supports custom error codes', function () {
    $exception = new StretchException('Error', 422);

    expect($exception->getCode())->toBe(422);
});

it('can be thrown and caught', function () {
    expect(fn () => throw new StretchException('Search failed'))
        ->toThrow(StretchException::class, 'Search failed');
});
