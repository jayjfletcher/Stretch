<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('builds a span_near of span_terms', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanNear([
            $s->spanTerm('text', 'quick'),
            $s->spanTerm('text', 'fox'),
        ], slop: 3, inOrder: true))
        ->build();

    $near = $query['query']['span_near'];

    expect($near['slop'])->toBe(3);
    expect($near['in_order'])->toBeTrue();
    expect($near['clauses'][0]['span_term']['text'])->toBe('quick');
    expect($near['clauses'][1]['span_term']['text'])->toBe('fox');
});

it('builds a span_first', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanFirst($s->spanTerm('text', 'intro'), end: 3))
        ->build();

    expect($query['query']['span_first']['end'])->toBe(3);
    expect($query['query']['span_first']['match']['span_term']['text'])->toBe('intro');
});

it('builds a span_or', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanOr([
            $s->spanTerm('text', 'a'),
            $s->spanTerm('text', 'b'),
        ]))
        ->build();

    expect($query['query']['span_or']['clauses'])->toHaveCount(2);
});

it('builds a span_not', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanNot(
            include: $s->spanTerm('text', 'apple'),
            exclude: $s->spanTerm('text', 'pie'),
            options: ['pre' => 1, 'post' => 1],
        ))
        ->build();

    expect($query['query']['span_not']['include']['span_term']['text'])->toBe('apple');
    expect($query['query']['span_not']['exclude']['span_term']['text'])->toBe('pie');
    expect($query['query']['span_not']['pre'])->toBe(1);
});

it('builds a span_within with a nested span_near', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanWithin(
            little: $s->spanTerm('text', 'fox'),
            big: $s->spanNear([
                $s->spanTerm('text', 'quick'),
                $s->spanTerm('text', 'brown'),
            ], slop: 5),
        ))
        ->build();

    expect($query['query']['span_within']['little']['span_term']['text'])->toBe('fox');
    expect($query['query']['span_within']['big']['span_near']['slop'])->toBe(5);
});

it('builds a span_multi wrapping a prefix', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(fn ($s) => $s->spanMulti(['prefix' => ['text' => ['value' => 'lara']]]))
        ->build();

    expect($query['query']['span_multi']['match']['prefix']['text']['value'])->toBe('lara');
});

it('falls back to the last built clause when the callback returns nothing', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->span(function ($s) {
            $s->spanTerm('text', 'solo');
        })
        ->build();

    expect($query['query']['span_term']['text'])->toBe('solo');
});
