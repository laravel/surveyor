<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Validator;
use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\AddsValidationRules;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesClosureReturnTypes;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\MultiType;
use Laravel\Surveyor\Types\Entities\InertiaRender;
use Laravel\Surveyor\Types\Entities\View;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\Node;
use Throwable;

class StaticCall extends AbstractResolver
{
    use AddsValidationRules, ResolvesClosureReturnTypes;

    public function resolve(Node\Expr\StaticCall $node)
    {
        $class = $this->from($node->class);
        $method = $node->name instanceof Node\Identifier ? $node->name->name : $this->from($node->name);

        if ($method === 'macro') {
            if (Type::is($class, ClassType::class)) {
                $this->handleMacro($class, $node);
            }
        }

        if ($class instanceof ClassType && $class->value === Validator::class && $method === 'make') {
            $this->addValidationRules($node->args[1]->value);
        }

        if ($class instanceof UnionType) {
            $class = $this->resolveUnion($class);
        }

        if ($class instanceof StringType) {
            return ($class->value === null) ? null : Type::mixed();
        }

        if ($method instanceof MultiType) {
            $returnTypes = [];

            foreach ($method->types as $type) {
                $returnTypes = array_merge(
                    $returnTypes,
                    $this->reflector->methodReturnType($class, $type->value, $node),
                );
            }

            return Type::union(...$returnTypes);
        }

        if ($class instanceof Condition) {
            $class = $class->type;
        }

        if (
            $method === 'make'
            && $class instanceof ClassType
            && $class->resolved() === Attribute::class
        ) {
            $attributeType = new ClassType(Attribute::class);

            if ($getArg = $this->findGetArgument($node->args)) {
                if ($getType = $this->resolveClosureReturnType($getArg->value)) {
                    $attributeType->setGenericTypes([$getType]);
                }
            }

            return $attributeType;
        }

        $returnTypes = array_merge(
            $this->handleEntities($class, $method, $node),
            $this->reflector->methodReturnType($class, $method, $node),
        );

        return Type::union(...$returnTypes);
    }

    protected function handleMacro(ClassType $class, Node\Expr\StaticCall $node): void
    {
        $macroName = $this->from($node->args[0]->value);
        $macroResolution = $this->from($node->args[1]->value);

        if (Type::is($macroName, StringType::class) && $macroName->value !== null) {
            $this->scope->addMacro($class->value, $macroName->value, $macroResolution);
        }
    }

    protected function handleEntities(ClassType $class, string $method, Node\Expr\StaticCall $node): array
    {
        $result = match ($class->value) {
            'Inertia\Inertia' => $this->handleInertiaEntity($method, $node),
            'Illuminate\Support\Facades\View' => $this->handleViewEntity($method, $node),
            default => [],
        };

        if (empty($result) && in_array($method, ['collection', 'make'], true)) {
            $result = $this->handleResourceStaticCall($class, isCollection: $method === 'collection');
        }

        return $result;
    }

    protected function handleResourceStaticCall(ClassType $class, bool $isCollection): array
    {
        $resolved = $class->resolved();

        if (! class_exists($resolved) || ! is_subclass_of($resolved, JsonResource::class)) {
            return [];
        }

        try {
            $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($resolved, isCollection: $isCollection);

            if ($resourceResponse) {
                return [$resourceResponse];
            }
        } catch (Throwable $e) {
            // Unable to resolve resource
        }

        return [];
    }

    protected function handleInertiaEntity(string $method, Node\Expr\StaticCall $node): array
    {
        if ($method !== 'render') {
            return [];
        }

        $args = array_map(fn ($arg) => $this->from($arg->value), $node->getArgs());

        return [
            new InertiaRender(
                $args[0]->value,
                $args[1] ?? Type::arrayShape(Type::string(), Type::mixed()),
            ),
        ];
    }

    protected function handleViewEntity(string $method, Node\Expr\StaticCall $node): array
    {
        if ($method !== 'render') {
            return [];
        }

        $args = array_map(fn ($arg) => $this->from($arg->value), $node->getArgs());

        return [
            new View(
                $args[0]->value,
                $args[1] ?? Type::arrayShape(Type::string(), Type::mixed()),
            ),
        ];
    }

    protected function findGetArgument(array $args): ?Node\Arg
    {
        foreach ($args as $arg) {
            if ($arg->name?->name === 'get') {
                return $arg;
            }
        }

        // Fall back to first positional argument
        if (isset($args[0]) && $args[0]->name === null) {
            return $args[0];
        }

        return null;
    }

    public function resolveForCondition(Node\Expr\StaticCall $node)
    {
        return $this->resolve($node);
    }

    protected function resolveUnion(UnionType $union)
    {
        foreach ($union->types as $type) {
            if ($type instanceof ClassType) {
                return $type;
            }

            if ($type instanceof StringType) {
                if (Util::isClassOrInterface($type->value)) {
                    return new ClassType($type->value);
                }

                $templateTag = $this->scope->getTemplateTag($type->value);

                if ($templateTag) {
                    return $templateTag->bound;
                }
            }
        }

        return null;
    }
}
