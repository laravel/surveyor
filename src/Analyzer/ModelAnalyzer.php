<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelInspector;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
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
    ) {
        //
    }

    public function mergeIntoResult(string $model, ClassResult $result, Scope $scope)
    {
        $this->reflector->setScope($scope);

        $info = $this->modelInspector->inspect($model);

        foreach ($info['attributes'] as $attribute) {
            $type = $this->resolveAttributeType($attribute, $model);
            $type->nullable($attribute['nullable'] ?? false);

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

            $classType = new ClassType($relation['related']);
            $classType->setGenericTypes([
                'TRelatedModel' => new ClassType($relation['related']),
                'TDeclaringModel' => new ClassType($model),
            ]);

            $methodResult = new MethodResult(
                $relation['name'],
            );
            $methodResult->flagAsModelRelation();
            $methodResult->addReturnType($classType, 0);

            $result->addMethod($methodResult);
        }
    }

    protected function resolveAttributeType(array $attribute, string $model): TypeContract
    {
        if ($attribute['cast']) {
            if ($attribute['cast'] === 'accessor') {
                return $this->resolveAccessorType($attribute, $model);
            }

            return $this->resolveCast($attribute['cast']);
        }

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
                if ($test === $attribute['type']) {
                    return $data[1];
                }

                if (str_contains($test, '/') && preg_match($test, $attribute['type']) === 1) {
                    return $data[1];
                }
            }
        }

        return Type::from($attribute['type']);
    }

    protected function resolveCast(string $cast): TypeContract
    {
        $result = match ($cast) {
            'json', 'encrypted:json', 'encrypted:array', 'encrypted:collection', 'array', 'encrypted:object' => Type::arrayShape(Type::mixed(), Type::mixed()),
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

        return $cast;
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

    protected function resolveAccessorType(array $attribute, string $model): TypeContract
    {
        $accessor = $attribute['name'];

        $possibleMethods = [
            'get'.str($accessor)->studly().'Attribute',
            str($accessor)->camel(),
        ];

        foreach ($possibleMethods as $method) {
            $returnType = $this->reflector->methodReturnType($model, $method);

            if ($returnType) {
                return Type::union(...$returnType);
            }
        }

        return Type::mixed();
    }
}
