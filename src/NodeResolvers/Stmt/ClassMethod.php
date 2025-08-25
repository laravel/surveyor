<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\Analysis\ReturnTypeAnalyzer;
use Laravel\StaticAnalyzer\Analysis\VariableAnalyzer;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Result\ClassMethodDeclaration;
use Laravel\StaticAnalyzer\Result\VariableTracker;
use PhpParser\Node;

class ClassMethod extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassMethod $node)
    {
        return (new ClassMethodDeclaration(
            name: $node->name->toString(),
            parameters: $this->getAllParameters($node),
            variables: $this->getAllVariables($node),
            returnTypes: $this->getAllReturnTypes($node),
        ))->fromNode($node);
    }

    protected function getAllParameters(Node\Stmt\ClassMethod $node)
    {
        if (count($node->params) === 0) {
            return [];
        }

        return array_map(fn($n) => $this->from($n), $node->params);
    }

    protected function getAllVariables(Node\Stmt\ClassMethod $node)
    {
        $analyzer = app(VariableAnalyzer::class);

        $analyzed = $analyzer->analyze($node);


        dd($analyzed);

        dd($analyzed->getVariableAtLine('whatever', 20));

        // Demo the new functionality
        foreach ([20, 23, 30, 32] as $line) {
            $possible = $tracker->getVariableAtLine('whatever', $line);
            // $unionTypeAtLine30 = $tracker->getUnionTypeAtLine('whatever', $line);
            // $description = $tracker->describeVariableAtLine('whatever', $line);

            dump($possible);
        }

        dd("asdf');");

        return $analyzer->analyze($node);
    }

    protected function getAllReturnTypes(Node\Stmt\ClassMethod $node)
    {
        $analyzer = app(ReturnTypeAnalyzer::class);

        return $analyzer->analyze($node);
    }
}
