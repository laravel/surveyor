<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;

class ResourcePagination
{
    /**
     * The links and meta Laravel adds when a resource collection wraps a paginator.
     */
    public function resolve(TypeContract $type): ?TypeContract
    {
        if (! $type instanceof ClassType) {
            return null;
        }

        $class = $type->resolved();
        $cursor = is_a($class, CursorPaginator::class, true);
        $lengthAware = is_a($class, LengthAwarePaginator::class, true);

        if (! $cursor && ! is_a($class, Paginator::class, true)) {
            return null;
        }

        $meta = [
            'path' => Type::string()->nullable(),
            'per_page' => Type::int(),
        ];

        if ($cursor) {
            $meta += [
                'next_cursor' => Type::string()->nullable(),
                'prev_cursor' => Type::string()->nullable(),
            ];
        } else {
            $meta += [
                'current_page' => Type::int(),
                'from' => Type::int()->nullable(),
                'to' => Type::int()->nullable(),
            ];
        }

        if ($lengthAware) {
            $meta += [
                'last_page' => Type::int(),
                'total' => Type::int(),
                'links' => Type::arrayShape(Type::int(), Type::array([
                    'url' => Type::string()->nullable(),
                    'label' => Type::string(),
                    'page' => Type::int()->nullable()->optional(),
                    'active' => Type::bool(),
                ])),
            ];
        } elseif (! $cursor) {
            // Early Laravel 12 releases do not send it yet.
            $meta['current_page_url'] = Type::string()->optional();
        }

        return Type::array([
            'links' => Type::array([
                'first' => Type::string()->nullable(),
                'last' => Type::string()->nullable(),
                'prev' => Type::string()->nullable(),
                'next' => Type::string()->nullable(),
            ]),
            'meta' => Type::array($meta),
        ]);
    }
}
