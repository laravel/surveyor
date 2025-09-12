<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt\TraitUseAdaptation;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Alias extends AbstractResolver
{
    public function resolve(Node\Stmt\TraitUseAdaptation\Alias $node)
    {
        dd($node, $node::class.' not implemented yet');
    }
}

// trait MyTrait
// {
//     public function hello()
//     {
//         return "Hello from trait!";
//     }

//     protected function goodbye()
//     {
//         return "Goodbye from trait!";
//     }
// }

// class MyClass
// {
//     use MyTrait {
//         hello as greet;              // Alias: hello() becomes greet()
//         goodbye as public farewell;  // Alias + visibility: goodbye() becomes public farewell()
//     }
// }

// $obj = new MyClass();
