<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('builds a geo_distance query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->geoDistance('pin.location', [-70, 40], '200km')
        ->build();

    expect($query['query']['geo_distance']['distance'])->toBe('200km');
    expect($query['query']['geo_distance']['pin.location'])->toBe([-70, 40]);
});

it('builds a geo_bounding_box query', function () {
    $box = ['top_left' => [-74.1, 40.73], 'bottom_right' => [-71.12, 40.01]];

    $query = (new ElasticsearchQueryBuilder)
        ->geoBoundingBox('pin.location', $box)
        ->build();

    expect($query['query']['geo_bounding_box']['pin.location'])->toBe($box);
});

it('builds a geo_shape query with a relation', function () {
    $shape = ['type' => 'envelope', 'coordinates' => [[-74.1, 40.73], [-71.12, 40.01]]];

    $query = (new ElasticsearchQueryBuilder)
        ->geoShape('location', $shape, relation: 'within')
        ->build();

    expect($query['query']['geo_shape']['location']['shape'])->toBe($shape);
    expect($query['query']['geo_shape']['location']['relation'])->toBe('within');
});

it('builds a percolate query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->percolate('query', ['message' => 'bonsai tree for sale'])
        ->build();

    expect($query['query']['percolate']['field'])->toBe('query');
    expect($query['query']['percolate']['document'])->toBe(['message' => 'bonsai tree for sale']);
});
