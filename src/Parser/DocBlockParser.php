<?php

namespace Laravel\StaticAnalyzer\Parser;

use Illuminate\Support\Collection;
use Laravel\StaticAnalyzer\Resolvers\DocBlockResolver;
// use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
// use Laravel\StaticAnalyzer\Types\Type as RangerType;
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

    public function __construct(
        protected DocBlockResolver $typeResolver,
    ) {
        $config = new ParserConfig(usedAttributes: []);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);

        $this->lexer = new Lexer($config);
        $this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    public function parseReturn(string $docBlock, ?CallLike $node = null): ?Collection
    {
        $this->node = $node;

        $this->parse($docBlock);

        $returnTypeValues = $this->parsed->getReturnTagValues();

        if (count($returnTypeValues) === 0) {
            return null;
        }

        // TODO: Format this output
        return collect($returnTypeValues)->map($this->resolve(...));
    }

    public function parseVar(string $docBlock) //: ?TypeContract
    {
        $this->parse($docBlock);

        $varTagValues = $this->parsed->getVarTagValues();

        if (count($varTagValues) === 0) {
            return null;
        }

        $result = collect($varTagValues)
            ->map(fn($tag) => $this->resolve($tag->type))
            ->unique();

        if ($result->count() === 1) {
            return $result->first();
        }

        dd('dockblock parseVar', $result);

        // return RangerType::union(...$result);
    }

    public function parseParam(string $docBlock, string $name) //: ?TypeContract
    {
        $this->parse($docBlock);

        $tagValues = $this->parsed->getParamTagValues();

        $value = collect($tagValues)
            ->first(fn($tag) => ltrim($tag->parameterName, '$') === ltrim($name, '$'));

        if ($value) {
            return $this->resolve($value->type);
        }

        return null;
    }

    public function parseProperties(string $docBlock): array
    {
        $this->parse($docBlock);

        $propertyTagValues = array_merge(
            $this->parsed->getPropertyTagValues(),
            $this->parsed->getPropertyReadTagValues(),
            $this->parsed->getPropertyWriteTagValues()
        );

        return collect($propertyTagValues)->mapWithKeys(fn($node) => [
            ltrim($node->propertyName, '$') => $this->resolve($node->type),
        ])->toArray();
    }

    public function parseMixins(string $docBlock): array
    {
        $this->parse($docBlock);

        return collect($this->parsed->getMixinTagValues())
            ->map(fn(MixinTagValueNode $node) => $this->resolve($node->type))
            ->all();
    }

    protected function parse(string $docBlock): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($docBlock));
        $this->parsed = $this->phpDocParser->parse($tokens);

        return $this->parsed;
    }

    protected function resolve($value) //: TypeContract|string
    {
        return $this->typeResolver->setParsed($this->parsed)->setReferenceNode($this->node)->from($value);
    }
}
