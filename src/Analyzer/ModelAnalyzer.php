<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\ModelInspector;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;

class ModelAnalyzer
{
    public function __construct(
        protected ModelInspector $modelInspector,
        protected Reflector $reflector,
        protected Analyzer $analyzer,
        protected ArrayableResolver $arrayableResolver,
    ) {
        //
    }

    public function mergeIntoResult(string $model, ClassLikeResult $result, Scope $scope)
    {
        $this->reflector->setScope($scope);

        $info = $this->modelInspector->inspect($model);

        foreach ($info['attributes'] as $attribute) {
            // An annotation is a deliberate statement about the attribute, so it beats
            // anything we infer from the cast or the column. Its nullability is part of
            // that statement, so the column's nullability is not applied over it.
            $type = $this->annotatedType($result, $attribute['name']);

            if ($type === null) {
                $type = $this->resolveAttributeType($attribute, $model, $result);

                if (isset($attribute['nullable'])) {
                    $type->nullable($attribute['nullable']);
                }
            }

            $result->addProperty(new PropertyResult($attribute['name'], $type, modelAttribute: true));
            $scope->state()->properties()->addManually($attribute['name'], $type, 0, 0, 0, 0);
        }

        foreach ($info['relations'] as $relation) {
            $isCollection = in_array($relation['type'], [
                'HasMany',
                'HasManyThrough',
                'BelongsToMany',
                'MorphToMany',
                'MorphedByMany',
            ]);

            if ($isCollection) {
                $type = Type::arrayShape(Type::int(), new ClassType($relation['related']));
                $result->addProperty(new PropertyResult($relation['name'], $type, modelRelation: true));
                $scope->state()->properties()->addManually($relation['name'], $type, 0, 0, 0, 0);
            } else {
                $type = new ClassType($relation['related']);
                $result->addProperty(new PropertyResult($relation['name'], $type, modelRelation: true));
                $scope->state()->properties()->addManually($relation['name'], $type, 0, 0, 0, 0);
            }

            $relationType = new ClassType('Illuminate\\Database\\Eloquent\\Relations\\'.$relation['type']);
            $relationType->setGenericTypes([
                'TRelatedModel' => new ClassType($relation['related']),
                'TDeclaringModel' => new ClassType($model),
            ]);

            $methodResult = new MethodResult(
                $relation['name'],
            );
            $methodResult->flagAsModelRelation();
            $methodResult->addReturnType($relationType, 0);

            $result->addMethod($methodResult);
        }
    }

    protected function annotatedType(ClassLikeResult $result, string $name): ?TypeContract
    {
        if (! $result->hasProperty($name)) {
            return null;
        }

        $property = $result->getProperty($name);

        // Eloquent's own protected properties ($casts, $fillable, ...) carry doc blocks
        // of their own and must never be mistaken for an attribute annotation.
        if (! $property->fromDocBlock || $property->visibility !== 'public') {
            return null;
        }

        return $property->type;
    }

    protected function resolveAttributeType(array $attribute, string $model, ClassLikeResult $result): TypeContract
    {
        if ($attribute['cast']) {
            if (in_array($attribute['cast'], ['accessor', 'attribute'])) {
                return $this->resolveAccessorType($attribute, $model, $result);
            }

            return $this->resolveCast($attribute['cast']);
        }

        return $this->resolveNonCast($attribute['type']);
    }

    protected function resolveNonCast(string $attributeType): TypeContract
    {
        $typeMapping = [
            [
                ['/^boolean(\\((0|1)\\))?/', '/^tinyint( unsigned)?(\\(\\d+\\))?$/', 'bool', 'boolean'],
                Type::bool(),
            ],
            [
                [
                    '/^(big)?serial/',
                    '/^(small|big)?int(eger)?( unsigned)?$/',
                    'real',
                    'money',
                    'double precision',
                    '/^(double|decimal|numeric)(\\(\\d+\\,\\d+\\))?/',
                    'int',
                    'integer',
                    'float',
                    'number',
                ],
                Type::int(),
            ],
            // 'Uint8Array' => ['bytea'],
            [
                [
                    'string',
                    'box',
                    'cidr',
                    'inet',
                    'line',
                    'lseg',
                    'path',
                    'time',
                    'uuid',
                    'year',
                    'point',
                    'circle',
                    'polygon',
                    'interval',
                    'datetime',
                    '/^json(b)?$/',
                    '/^date(time)?$/',
                    '/^macaddr(8)?$/',
                    '/^(long|medium)?text$/',
                    '/^(var)?char(acter)?( varying)??(\\(\\d+\\))?/',
                    '/^time(stamp)?(\\(\\d+\\))?( (with|without) time zone)?/',
                ],
                Type::string(),
            ],
        ];

        foreach ($typeMapping as $data) {
            foreach ($data[0] as $test) {
                if ($test === $attributeType) {
                    return $data[1];
                }

                // @phpstan-ignore-next-line
                if (str_contains($test, '/') && preg_match($test, $attributeType) === 1) {
                    return $data[1];
                }
            }
        }

        return Type::from($attributeType);
    }

    protected function resolveCast(string $cast): TypeContract
    {
        $result = match ($cast) {
            // These all decode JSON, which yields either a list or a keyed map. Picking one
            // is a guess that types half of all usage wrongly, so both are offered and the
            // consumer narrows. An annotation on the model beats this entirely.
            'array', 'encrypted:array',
            'json', 'json:unicode', 'encrypted:json',
            'collection', 'encrypted:collection' => Type::union(
                Type::arrayShape(Type::int(), Type::mixed()),
                Type::arrayShape(Type::mixed(), Type::mixed()),
            ),
            // Decoded without associative mode, so always an object.
            'object', 'encrypted:object' => Type::arrayShape(Type::mixed(), Type::mixed()),
            'timestamp', 'int', 'integer', 'float' => Type::int(),
            'attribute', 'encrypted' => Type::mixed(),
            'hashed', 'date', 'datetime', 'immutable_date', 'immutable_datetime',  'string' => Type::string(),
            'bool', 'boolean' => Type::bool(),
            default => null,
        };

        if ($result) {
            return $result;
        }

        if (class_exists($cast)) {
            return Type::from($this->resolveClassCast($cast));
        }

        return $this->resolveNonCast($cast);
    }

    protected function resolveClassCast(string $cast): TypeContract
    {
        $analyzed = $this->analyzer->analyzeClass($cast)->result();

        if ($analyzed === null) {
            return Type::string($cast);
        }

        if ($analyzed->implements(CastsAttributes::class)) {
            return $analyzed->getMethod('get')->returnType();
        }

        if ($analyzed->implements(Arrayable::class)) {
            return $analyzed->getMethod('toArray')->returnType();
        }

        return Type::string($cast);
    }

    protected function resolveAccessorType(array $attribute, string $model, ClassLikeResult $result): TypeContract
    {
        $accessor = $attribute['name'];

        $possibleMethods = [
            'get'.str($accessor)->studly().'Attribute',
            str($accessor)->camel()->toString(),
        ];

        $reflection = $this->reflector->reflectClass($model);

        foreach ($possibleMethods as $method) {
            if (! $reflection->hasMethod($method)) {
                continue;
            }

            // First try analyzed return types — these capture generic types set during AST analysis
            // (e.g. Attribute::make(get: fn(): string => ...) → Attribute<string>)
            if ($result->hasMethod($method)) {
                foreach ($result->getMethod($method)->returnTypes() as $analyzedReturnType) {
                    if ($extractedType = $this->extractAttributeGenericType($analyzedReturnType['type'])) {
                        return $this->arrayableResolver->resolve($extractedType) ?? $extractedType;
                    }
                }
            }

            // Fall back to reflector (PHPDoc @return Attribute<T> generics)
            $returnTypes = $this->reflector->methodReturnType($model, $method);

            if (! $returnTypes) {
                continue;
            }

            foreach ($returnTypes as $returnType) {
                $extractedType = $this->extractAttributeGenericType($returnType);

                if ($extractedType) {
                    return $this->arrayableResolver->resolve($extractedType) ?? $extractedType;
                }
            }

            return Type::union(...$returnTypes);
        }

        return Type::mixed();
    }

    protected function extractAttributeGenericType(TypeContract $type): ?TypeContract
    {
        if (! $type instanceof ClassType) {
            return null;
        }

        if ($type->resolved() !== Attribute::class) {
            return null;
        }

        $genericTypes = $type->genericTypes();

        if (empty($genericTypes)) {
            return null;
        }

        $getterType = reset($genericTypes);

        return $getterType ?: null;
    }
}
