<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Property extends AbstractResolver
{
    public function resolve(Node\Stmt\Property $node)
    {
        foreach ($node->props as $prop) {
            $types = [];

            if ($node->getDocComment()) {
                $docType = $this->docBlockParser->parseVar($node->getDocComment());

                if ($docType) {
                    $types[] = $docType;
                }
            }

            if ($node->type) {
                $types[] = $this->from($node->type);
            }

            if (empty($types) && $prop->default) {
                $types[] = $this->from($prop->default);
            }

            $unionType = Type::union(...$types);

            $this->scope->state()->add(
                $prop,
                $unionType,
            );

            $result = $this->scope->result();

            if (! $result instanceof ClassLikeResult) {
                continue;
            }

            $result->addProperty(
                new PropertyResult(
                    name: $prop->name,
                    type: $unionType,
                    visibility: match (true) {
                        $node->isProtected() => 'protected',
                        $node->isPrivate() => 'private',
                        default => 'public',
                    },
                    fromDocBlock: $node->getDocComment() ? true : false,
                ),
            );
        }

        return null;
    }
}
