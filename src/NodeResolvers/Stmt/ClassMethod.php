<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRuleParser;
use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class ClassMethod extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassMethod $node)
    {
        $this->scope->setMethodName($node->name);
        $this->scope->setEntityType(EntityType::METHOD_TYPE);

        $result = new MethodResult(
            name: $this->scope->methodName(),
        );

        $this->scope->attachResult($result);

        if ($node->returnType && $returnTypes = $this->from($node->returnType)) {
            $this->scope->addReturnType($returnTypes, $node->getStartLine());
        }

        if ($node->name == '__construct') {
            foreach ($node->params as $param) {
                if (! $param->isPromoted()) {
                    continue;
                }

                $this->scope->parent()->result()->addProperty(
                    new PropertyResult(
                        name: $param->var->name,
                        type: $this->from($param->type),
                        visibility: match (true) {
                            $param->isProtected() => 'protected',
                            $param->isPrivate() => 'private',
                            default => 'public',
                        },
                    ),
                );
            }
        }

        return null;
    }

    public function scope(): Scope
    {
        return $this->scope->newChildScope();
    }

    public function exitScope(): Scope
    {
        foreach ($this->scope->parameters() as $parameter) {
            $this->scope->result()->addParameter($parameter->name, $parameter->type);
        }

        $isFormRequestRules = in_array(FormRequest::class, $this->scope->parent()->extends()) && $this->scope->methodName() === 'rules';

        foreach ($this->scope->returnTypes() as $returnType) {
            $this->scope->result()->addReturnType($returnType['type'], $returnType['lineNumber']);

            if ($isFormRequestRules && Type::is($returnType['type'], ArrayType::class)) {
                foreach ($returnType['type']->value as $key => $value) {
                    if ($value instanceof StringType) {
                        $this->scope->result()->addValidationRule(
                            $key,
                            array_map(
                                fn ($subRule) => ValidationRuleParser::parse($subRule),
                                explode('|', $value->value),
                            ),
                        );
                    } elseif ($value instanceof ArrayType) {
                        $this->scope->result()->addValidationRule(
                            $key,
                            array_values(array_filter(array_map($this->parseSubRule(...), $value->value))),
                        );
                    }
                }
            }
        }

        if (($parentResult = $this->scope->parent()?->result())
            && ($scopeResult = $this->scope->result()) instanceof MethodResult) {
            $parentResult->addMethod($scopeResult);
        }

        return $this->scope->parent();
    }

    protected function parseSubRule(TypeContract $subRule)
    {
        if (Type::is($subRule, StringType::class)) {
            return ValidationRuleParser::parse($subRule->value);
        }

        // Class based rules are not supported yet
        return null;
    }
}
