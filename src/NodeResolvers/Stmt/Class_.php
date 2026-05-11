<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzer\ModelAnalyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ParsesClassLikeDocBlock;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use Throwable;

class Class_ extends AbstractResolver
{
    use ParsesClassLikeDocBlock;

    public function resolve(Node\Stmt\Class_ $node)
    {
        // Anonymous classes (`new class { ... }`) have no name and no
        // namespacedName. Skip them: their inner methods will fall through
        // to the ClassMethod resolver where the `instanceof ClassLikeResult`
        // parent guard will drop them rather than crashing.
        if ($node->name === null) {
            return null;
        }

        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::CLASS_TYPE);

        $this->parseImplements($node);
        $this->parseExtends($node);

        $result = new ClassLikeResult(
            name: $this->scope->entityName(),
            namespace: $this->scope->namespace(),
            extends: $this->scope->extends(),
            implements: $this->scope->implements(),
            uses: $this->scope->uses(),
            filePath: $this->scope->fullPath(),
            entityType: EntityType::CLASS_TYPE,
        );

        $this->scope->attachResult($result);

        $this->parseClassLikeDocBlock($node, $result);

        if ($this->extendsResource()) {
            try {
                app(ResourceAnalyzer::class)->injectModelProperties($result->name(), $result, $this->scope);
            } catch (Throwable $e) {
                // Model resolution failed
            }
        }

        return null;
    }

    public function onExit(NodeAbstract $node): void
    {
        $result = $this->scope->result();

        if (! $result instanceof ClassLikeResult) {
            return;
        }

        if (in_array(Model::class, $this->scope->extends())) {
            try {
                app(ModelAnalyzer::class)->mergeIntoResult($result->name(), $result, $this->scope);
            } catch (Throwable $e) {
                // Unable to inspect model, possibly due to missing database connection
            }
        }

        if ($this->extendsResource()) {
            try {
                app(ResourceAnalyzer::class)->resolveDataShape($result->name(), $result, $this->scope);
            } catch (Throwable $e) {
                // Unable to resolve resource data shape
            }
        }
    }

    protected function extendsResource(): bool
    {
        return array_intersect(
            [JsonResource::class, ResourceCollection::class],
            $this->scope->extends(),
        ) !== [];
    }

    protected function parseImplements(Node\Stmt\Class_ $node)
    {
        foreach ($node->implements as $interface) {
            $this->scope->addImplement($interface->toString());

            try {
                $reflection = $this->reflector->reflectClass($interface->toString());
            } catch (Throwable $e) {
                continue;
            }

            foreach ($reflection->getConstants() as $key => $value) {
                $this->scope->addConstant($key, Type::from($value));
            }
        }
    }

    protected function parseExtends(Node\Stmt\Class_ $node)
    {
        if (! $node->extends) {
            return;
        }

        $extends = [$node->extends->toString()];

        try {
            $extendsClass = $this->reflector->reflectClass($node->extends->toString());

            do {
                $extendsClass = $extendsClass->getParentClass();

                if ($extendsClass) {
                    $extends[] = $extendsClass->getName();
                }
            } while ($extendsClass);
        } catch (Throwable $e) {
            // Reflection failed; keep the directly-declared parent only.
        }

        foreach ($extends as $extend) {
            $this->scope->addExtend($extend);
        }
    }
}
