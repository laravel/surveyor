<?php

namespace Laravel\Surveyor\Parser;

use Illuminate\Support\Arr;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Resolvers\DocBlockResolver;
use Laravel\Surveyor\Types\Type;
// use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
// use Laravel\Surveyor\Types\Type as RangerType;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\MixinTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

class DocBlockParser
{
    protected ?CallLike $node = null;

    protected PhpDocNode $parsed;

    protected Lexer $lexer;

    protected PhpDocParser $phpDocParser;

    protected Scope $scope;

    protected array $cached = [];

    public function __construct(
        protected DocBlockResolver $typeResolver,
    ) {
        $config = new ParserConfig(usedAttributes: []);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);

        $this->lexer = new Lexer($config);
        $this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    public function setScope(Scope $scope)
    {
        $this->scope = $scope;
    }

    public function parseReturn(string $docBlock, ?CallLike $node = null): array
    {
        $this->node = $node;
        $this->parse($docBlock);

        $returnTypeValues = $this->parsed->getReturnTagValues();

        return array_map($this->resolve(...), $returnTypeValues);
    }

    public function parseVar(string $docBlock) // : ?TypeContract
    {
        $this->parse($docBlock);

        $varTagValues = $this->parsed->getVarTagValues();

        if (count($varTagValues) === 0) {
            return null;
        }

        $result = array_values(
            array_unique(
                array_map(
                    fn ($tag) => $this->resolve($tag),
                    $varTagValues,
                ),
            ),
        );

        if (count($result) === 1) {
            return $result[0];
        }

        return Type::union(...$result);
    }

    public function parseParam(string $docBlock, string $name) // : ?TypeContract
    {
        $this->parse($docBlock);

        $paramTags = $this->parsed->getParamTagValues();

        $this->parseTemplateTags($docBlock);

        $value = Arr::first(
            $paramTags,
            fn ($tag) => ltrim($tag->parameterName, '$') === ltrim($name, '$')
        );

        if ($value) {
            return $this->resolve($value);
        }

        return null;
    }

    public function parseTemplateTags(string $docBlock): array
    {
        $this->parse($docBlock);

        $templateTags = array_map(fn ($tag) => $this->resolve($tag), $this->parsed->getTemplateTagValues());

        $this->scope->setTemplateTags($templateTags);

        return $this->parsed->getTemplateTagValues();
    }

    public function parseProperties(string $docBlock): array
    {
        $this->parse($docBlock);

        $propertyTagValues = array_merge(
            $this->parsed->getPropertyTagValues(),
            $this->parsed->getPropertyReadTagValues(),
            $this->parsed->getPropertyWriteTagValues()
        );

        $result = [];

        foreach ($propertyTagValues as $node) {
            $result[ltrim($node->propertyName, '$')] = $this->resolve($node);
        }

        return $result;
    }

    public function parseMethods(string $docBlock): array
    {
        $this->parse($docBlock);

        $result = [];

        foreach ($this->parsed->getMethodTagValues() as $value) {
            $result[$value->methodName] = $this->resolve($value->returnType);
        }

        return $result;
    }

    public function parseMixins(string $docBlock): array
    {
        $this->parse($docBlock);

        return array_map(
            fn (MixinTagValueNode $node) => $this->resolve($node->type),
            $this->parsed->getMixinTagValues(),
        );
    }

    protected function parse(string $docBlock): PhpDocNode
    {
        if (isset($this->cached[$docBlock])) {
            return $this->cached[$docBlock];
        }

        $tokens = new TokenIterator($this->lexer->tokenize($docBlock));
        $this->parsed = $this->phpDocParser->parse($tokens);

        $this->cached[$docBlock] = $this->parsed;

        return $this->parsed;
    }

    protected function resolve($value) // : TypeContract|string
    {
        return $this->typeResolver->setParsed($this->parsed)->setReferenceNode($this->node)->from($value, $this->scope);
    }
}
