<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use PhpParser\Node;

trait ParsesClassLikeDocBlock
{
    protected function parseClassLikeDocBlock(Node\Stmt\ClassLike $node, ClassLikeResult $result): void
    {
        if (! $node->getDocComment()) {
            return;
        }

        $properties = $this->docBlockParser->parseProperties($node->getDocComment());

        foreach ($properties as $name => $details) {
            $this->scope->state()->addDocBlockProperty($name, $details['type']);
            $result->addProperty(new PropertyResult(
                name: $name,
                type: $details['type'],
                fromDocBlock: true,
                readOnly: $details['readOnly'],
                writeOnly: $details['writeOnly'],
            ));
        }

        $methods = $this->docBlockParser->parseMethods($node->getDocComment());

        foreach ($methods as $name => $type) {
            $scope = $this->scope->newChildScope();
            $scope->setMethodName($name);
            $scope->setEntityType(EntityType::METHOD_TYPE);
            $scope->addReturnType($type, 0);

            $methodResult = new MethodResult(
                name: $scope->methodName(),
            );

            foreach ($scope->parameters() as $parameter) {
                $methodResult->addParameter($parameter->name, $parameter->type);
            }

            foreach ($scope->returnTypes() as $returnType) {
                $methodResult->addReturnType($returnType['type'], $returnType['lineNumber']);
            }

            $result->addMethod($methodResult);
        }

        foreach ($this->docBlockParser->resolveTemplateTags($node->getDocComment()) as $tag) {
            $result->addTemplateTag($tag);
        }
    }
}
