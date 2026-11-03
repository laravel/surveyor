<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesIgnoreMarkers;
use PhpParser\Node;

class Trait_ extends AbstractResolver
{
    use ResolvesIgnoreMarkers;

    public function resolve(Node\Stmt\Trait_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::TRAIT_TYPE);

        // Without a result to collect them, the trait's methods are analyzed
        // and then dropped, leaving nothing to ask about what they return. It
        // is attached to the scope of the file, where a class or an interface
        // keeps its own, since that is the scope an analysis hands back.
        $result = new ClassLikeResult(
            name: $this->scope->entityName(),
            namespace: $this->scope->namespace(),
            extends: [],
            implements: [],
            uses: $this->scope->uses(),
            filePath: $this->scope->fullPath(),
            entityType: EntityType::TRAIT_TYPE,
        );

        if ($marker = $this->ignoreMarker($node)) {
            $result->flagAsIgnored($marker);
        }

        $this->scope->attachResult($result);

        return null;
    }
}
