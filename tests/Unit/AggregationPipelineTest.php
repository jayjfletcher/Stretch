<?php

declare(strict_types=1);

use JayI\Stretch\Builders\AggregationBuilder;

it('builds a percentiles aggregation', function () {
    $agg = (new AggregationBuilder)->percentiles('load_time', [95, 99])->build();

    expect($agg)->toBe(['percentiles' => ['field' => 'load_time', 'percents' => [95, 99]]]);
});

it('builds an extended_stats aggregation', function () {
    $agg = (new AggregationBuilder)->extendedStats('grade')->build();

    expect($agg)->toBe(['extended_stats' => ['field' => 'grade']]);
});

it('builds geo_bounds and geo_centroid aggregations', function () {
    expect((new AggregationBuilder)->geoBounds('location')->build())
        ->toBe(['geo_bounds' => ['field' => 'location']]);

    expect((new AggregationBuilder)->geoCentroid('location')->build())
        ->toBe(['geo_centroid' => ['field' => 'location']]);
});

it('builds a filter bucket aggregation with a sub-aggregation', function () {
    $agg = (new AggregationBuilder)
        ->filter(fn ($q) => $q->term('status', 'published'))
        ->subAggregation('avg_price', fn ($s) => $s->avg('price'))
        ->build();

    expect($agg['filter']['term']['status'])->toBe('published');
    expect($agg['aggs']['avg_price'])->toBe(['avg' => ['field' => 'price']]);
});

it('builds a nested bucket aggregation', function () {
    $agg = (new AggregationBuilder)->nested('comments')->build();

    expect($agg)->toBe(['nested' => ['path' => 'comments']]);
});

it('builds a derivative pipeline aggregation', function () {
    $agg = (new AggregationBuilder)->derivative('sales')->build();

    expect($agg)->toBe(['derivative' => ['buckets_path' => 'sales']]);
});

it('builds a cumulative_sum pipeline aggregation', function () {
    $agg = (new AggregationBuilder)->cumulativeSum('sales')->build();

    expect($agg)->toBe(['cumulative_sum' => ['buckets_path' => 'sales']]);
});

it('builds a moving_fn pipeline aggregation', function () {
    $agg = (new AggregationBuilder)
        ->movingFn('the_sum', 'MovingFunctions.unweightedAvg(values)', 10)
        ->build();

    expect($agg['moving_fn'])->toBe([
        'buckets_path' => 'the_sum',
        'script' => 'MovingFunctions.unweightedAvg(values)',
        'window' => 10,
    ]);
});

it('builds a bucket_script pipeline aggregation', function () {
    $agg = (new AggregationBuilder)
        ->bucketScript(['sales' => 'total_sales', 'count' => '_count'], 'params.sales / params.count')
        ->build();

    expect($agg['bucket_script']['buckets_path'])->toBe(['sales' => 'total_sales', 'count' => '_count']);
    expect($agg['bucket_script']['script'])->toBe('params.sales / params.count');
});

it('builds a bucket_selector pipeline aggregation', function () {
    $agg = (new AggregationBuilder)
        ->bucketSelector(['t' => 'total'], 'params.t > 100')
        ->build();

    expect($agg['bucket_selector']['script'])->toBe('params.t > 100');
});

it('builds a bucket_sort pipeline aggregation', function () {
    $agg = (new AggregationBuilder)
        ->bucketSort([['total_sales' => ['order' => 'desc']]], ['size' => 5])
        ->build();

    expect($agg['bucket_sort']['sort'])->toBe([['total_sales' => ['order' => 'desc']]]);
    expect($agg['bucket_sort']['size'])->toBe(5);
});

it('builds a sibling pipeline bucket aggregation', function () {
    $agg = (new AggregationBuilder)
        ->pipelineBucket('avg_bucket', 'sales_per_month>sales')
        ->build();

    expect($agg)->toBe(['avg_bucket' => ['buckets_path' => 'sales_per_month>sales']]);
});
