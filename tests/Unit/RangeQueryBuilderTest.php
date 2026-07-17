<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Builders\RangeQueryBuilder;

it('can build a gt range condition', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->gt(100);

    $result = $range->build();

    expect($result['range']['price']['gt'])->toBe(100);
});

it('can build a gte range condition', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->gte(100);

    $result = $range->build();

    expect($result['range']['price']['gte'])->toBe(100);
});

it('can build a lt range condition', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->lt(500);

    $result = $range->build();

    expect($result['range']['price']['lt'])->toBe(500);
});

it('can build a lte range condition', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->lte(500);

    $result = $range->build();

    expect($result['range']['price']['lte'])->toBe(500);
});

it('can chain multiple range conditions', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->gte(100)->lte(500);

    $result = $range->build();

    expect($result['range']['price']['gte'])->toBe(100);
    expect($result['range']['price']['lte'])->toBe(500);
});

it('can combine gt and lt for exclusive range', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    $range->gt(100)->lt(500);

    $result = $range->build();

    expect($result['range']['price']['gt'])->toBe(100);
    expect($result['range']['price']['lt'])->toBe(500);
});

it('can set timezone for date range queries', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'created_at');

    $range->gte('2024-01-01')->lte('2024-12-31')->timezone('America/New_York');

    $result = $range->build();

    expect($result['range']['created_at']['gte'])->toBe('2024-01-01');
    expect($result['range']['created_at']['lte'])->toBe('2024-12-31');
    expect($result['range']['created_at']['time_zone'])->toBe('America/New_York');
});

it('can set format for date range queries', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'created_at');

    $range->gte('01/01/2024')->lte('12/31/2024')->format('MM/dd/yyyy');

    $result = $range->build();

    expect($result['range']['created_at']['gte'])->toBe('01/01/2024');
    expect($result['range']['created_at']['lte'])->toBe('12/31/2024');
    expect($result['range']['created_at']['format'])->toBe('MM/dd/yyyy');
});

it('can combine timezone and format', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'created_at');

    $range->gte('2024-01-01')
        ->lte('2024-12-31')
        ->timezone('UTC')
        ->format('yyyy-MM-dd');

    $result = $range->build();

    expect($result['range']['created_at']['time_zone'])->toBe('UTC');
    expect($result['range']['created_at']['format'])->toBe('yyyy-MM-dd');
});

it('returns the parent query builder via getParent', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'price');

    expect($range->getParent())->toBe($parent);
});

it('adds range query to parent builder', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->range('price')->gte(100)->lt(500);

    $query = $builder->build();

    expect($query['query']['range']['price']['gte'])->toBe(100);
    expect($query['query']['price']['lt'] ?? $query['query']['range']['price']['lt'])->toBe(500);
});

it('works with date string values', function () {
    $parent = new ElasticsearchQueryBuilder;
    $range = new RangeQueryBuilder($parent, 'timestamp');

    $range->gte('now-1d')->lte('now');

    $result = $range->build();

    expect($result['range']['timestamp']['gte'])->toBe('now-1d');
    expect($result['range']['timestamp']['lte'])->toBe('now');
});

it('does not attach an empty range clause when only timezone or format is set', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->range('created_at')->timezone('UTC')->format('yyyy-MM-dd');

    $query = $builder->build();

    // No bound was ever added, so no range clause should reach the parent.
    expect($query)->not->toHaveKey('query');
});

it('attaches timezone and format once a bound exists', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->range('created_at')->timezone('UTC')->gte('2024-01-01');

    $query = $builder->build();

    expect($query['query']['range']['created_at']['gte'])->toBe('2024-01-01')
        ->and($query['query']['range']['created_at']['time_zone'])->toBe('UTC');
});

it('keeps interleaved range builders for the same field independent', function () {
    $builder = new ElasticsearchQueryBuilder;

    $first = $builder->range('price');
    $second = $builder->range('price');

    $first->gte(100);
    $second->lte(500);
    $first->lt(200);

    $query = $builder->build();

    // Two distinct range clauses must survive under bool.must, not clobber.
    $ranges = collect($query['query']['bool']['must'] ?? [])
        ->filter(fn ($clause) => isset($clause['range']['price']))
        ->map(fn ($clause) => $clause['range']['price'])
        ->values();

    expect($ranges)->toHaveCount(2)
        ->and($ranges[0])->toBe(['gte' => 100, 'lt' => 200])
        ->and($ranges[1])->toBe(['lte' => 500]);
});
