<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ParsesClassLikeDocBlock;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use Throwable;

class Interface_ extends AbstractResolver
{
    use ParsesClassLikeDocBlock;

    public function resolve(Node\Stmt\Interface_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::INTERFACE_TYPE);

        $this->parseExtends($node);

        $result = new ClassResult(
            name: $this->scope->entityName(),
            namespace: $this->scope->namespace(),
            extends: $this->scope->extends(),
            implements: $this->scope->implements(),
            uses: $this->scope->uses(),
            filePath: $this->scope->fullPath(),
            entityType: EntityType::INTERFACE_TYPE,
        );

        $this->scope->attachResult($result);

        $this->parseClassLikeDocBlock($node, $result);

        return null;
    }

    protected function parseExtends(Node\Stmt\Interface_ $node)
    {
        foreach ($node->extends as $extend) {
            $this->scope->addExtend($extend->toString());

            try {
                $reflection = $this->reflector->reflectClass($extend->toString());
            } catch (Throwable $e) {
                continue;
            }

            // Interfaces support multiple inheritance — flatten the full
            // ancestor set so consumers can ask `$result->extends($parent)`
            // for any transitive parent.
            foreach ($reflection->getInterfaceNames() as $name) {
                $this->scope->addExtend($name);
            }

            foreach ($reflection->getConstants() as $key => $value) {
                $this->scope->addConstant($key, Type::from($value));
            }
        }
    }
}
