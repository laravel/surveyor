<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use Throwable;

class New_ extends AbstractResolver
{
    public function resolve(Node\Expr\New_ $node)
    {
        $type = $this->from($node->class);

        if (! property_exists($type, 'value') || $type->value === null) {
            // We couldn't figure it out
            return Type::mixed();
        }

        $classType = new ClassType($this->scope->getUse($type->value));

        $classType->setConstructorArguments(array_map(
            fn ($arg) => $this->from($arg->value),
            $node->args,
        ));

        // Check if this is a resource class instantiation
        $resolved = $classType->resolved();

        if (class_exists($resolved) && is_subclass_of($resolved, JsonResource::class)) {
            try {
                $resourceResponse = app(ResourceAnalyzer::class)->buildResourceResponse($resolved);

                if ($resourceResponse) {
                    return $resourceResponse;
                }
            } catch (Throwable $e) {
                // Fall through to return plain ClassType
            }
        }

        return $classType;
    }

    public function resolveForCondition(Node\Expr\New_ $node)
    {
        return $this->resolve($node);
    }
}
