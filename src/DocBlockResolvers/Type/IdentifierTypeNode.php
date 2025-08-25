<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PHPStan\PhpDocParser\Ast;

class IdentifierTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\IdentifierTypeNode $node)
    {
        $name = (string) $node->name;
        // $templates = $this->parsed->getTemplateTagValues();

        // $matchingTemplate = collect($templates)->first(fn($template) => $template->name === $name);

        // if ($matchingTemplate) {
        //     // dd('Found template type: ' . $matchingTemplate);
        // }

        return Type::from($name);
    }
}
