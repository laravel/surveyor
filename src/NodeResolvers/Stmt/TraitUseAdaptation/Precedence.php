<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt\TraitUseAdaptation;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Precedence extends AbstractResolver
{
    public function resolve(Node\Stmt\TraitUseAdaptation\Precedence $node)
    {
        dd($node, $node::class.' not implemented yet');
    }
}

// trait A
// {
//     public function hello()
//     {
//         return "Hello from A";
//     }
// }

// trait B
// {
//     public function hello()
//     {
//         return "Hello from B";
//     }
// }

// class MyClass
// {
//     use A, B {
//         A::hello insteadof B;  // This creates a Precedence node
//     }
// }
