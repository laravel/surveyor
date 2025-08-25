<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class ReturnTypeAnalyzer extends AbstractResolver
{
    protected array $returnTypes = [];

    public function analyze(Node\Stmt\ClassMethod $methodNode): array
    {
        $this->returnTypes = [];

        // Add declared return type if present
        if ($methodNode->returnType) {
            $this->returnTypes[] = $this->from($methodNode->returnType);
        }

        // Find all return statements throughout the method
        if ($methodNode->stmts) {
            $this->processStatements($methodNode->stmts);
        }

        return $this->returnTypes;
    }

    protected function processStatements(array $statements): void
    {
        foreach ($statements as $stmt) {
            $this->processStatement($stmt);
        }
    }

    protected function processStatement(Node $stmt): void
    {
        switch (true) {
            case $stmt instanceof Node\Stmt\Return_:
                $this->processReturnStatement($stmt);
                break;

            case $stmt instanceof Node\Stmt\If_:
                $this->processIfStatement($stmt);
                break;

            case $stmt instanceof Node\Stmt\While_:
            case $stmt instanceof Node\Stmt\For_:
            case $stmt instanceof Node\Stmt\Foreach_:
                $this->processLoopStatement($stmt);
                break;

            case $stmt instanceof Node\Stmt\Switch_:
                $this->processSwitchStatement($stmt);
                break;

            case $stmt instanceof Node\Stmt\TryCatch:
                $this->processTryCatchStatement($stmt);
                break;

            case $stmt instanceof Node\Stmt\Expression:
                // Check if the expression contains any nested statements
                $this->processExpression($stmt->expr);
                break;

            default:
                // Handle any other statements that might contain nested code
                $this->processGenericStatement($stmt);
        }
    }

    protected function processReturnStatement(Node\Stmt\Return_ $returnStmt): void
    {
        if ($returnStmt->expr) {
            $type = $this->inferTypeFromExpression($returnStmt->expr);
            $this->returnTypes[] = $type;
        } else {
            // Return without expression is void
            $this->returnTypes[] = Type::from('void');
        }
    }

    protected function processIfStatement(Node\Stmt\If_ $ifStmt): void
    {
        // Process if body
        if ($ifStmt->stmts) {
            $this->processStatements($ifStmt->stmts);
        }

        // Process elseif branches
        foreach ($ifStmt->elseifs as $elseif) {
            if ($elseif->stmts) {
                $this->processStatements($elseif->stmts);
            }
        }

        // Process else branch
        if ($ifStmt->else && $ifStmt->else->stmts) {
            $this->processStatements($ifStmt->else->stmts);
        }
    }

    protected function processLoopStatement(Node $loopStmt): void
    {
        // Process loop body
        if (property_exists($loopStmt, 'stmts') && $loopStmt->stmts) {
            $this->processStatements($loopStmt->stmts);
        }
    }

    protected function processSwitchStatement(Node\Stmt\Switch_ $switchStmt): void
    {
        foreach ($switchStmt->cases as $case) {
            if ($case->stmts) {
                $this->processStatements($case->stmts);
            }
        }
    }

    protected function processTryCatchStatement(Node\Stmt\TryCatch $tryCatchStmt): void
    {
        // Process try block
        if ($tryCatchStmt->stmts) {
            $this->processStatements($tryCatchStmt->stmts);
        }

        // Process catch blocks
        foreach ($tryCatchStmt->catches as $catch) {
            if ($catch->stmts) {
                $this->processStatements($catch->stmts);
            }
        }

        // Process finally block
        if ($tryCatchStmt->finally && $tryCatchStmt->finally->stmts) {
            $this->processStatements($tryCatchStmt->finally->stmts);
        }
    }

    protected function processExpression(Node\Expr $expr): void
    {
        // Handle expressions that might contain closures with returns
        if ($expr instanceof Node\Expr\Closure) {
            if ($expr->stmts) {
                $this->processStatements($expr->stmts);
            }
        } elseif ($expr instanceof Node\Expr\ArrowFunction) {
            // Arrow functions have implicit returns
            $type = $this->inferTypeFromExpression($expr->expr);
            $this->returnTypes[] = $type;
        }
    }

    protected function processGenericStatement(Node $stmt): void
    {
        // Use reflection to find any properties that might contain statements
        $reflection = new \ReflectionObject($stmt);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($stmt);

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node) {
                        $this->processStatement($item);
                    }
                }
            } elseif ($value instanceof Node) {
                $this->processStatement($value);
            }
        }
    }

    protected function inferTypeFromExpression(Node\Expr $expr): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        return match (true) {
            $expr instanceof Node\Scalar\String_ => Type::string($expr->value),
            $expr instanceof Node\Scalar\LNumber => Type::int(),
            $expr instanceof Node\Scalar\DNumber => Type::from('float'),
            $expr instanceof Node\Expr\Array_ => Type::array([]),
            $expr instanceof Node\Expr\ConstFetch => $this->inferTypeFromConstant($expr),
            $expr instanceof Node\Expr\FuncCall => $this->inferTypeFromFunctionCall($expr),
            $expr instanceof Node\Expr\MethodCall => $this->inferTypeFromMethodCall($expr),
            $expr instanceof Node\Expr\StaticCall => $this->inferTypeFromStaticCall($expr),
            $expr instanceof Node\Expr\New_ => $this->inferTypeFromNew($expr),
            $expr instanceof Node\Expr\Variable => Type::mixed(), // Could be improved with variable tracking
            $expr instanceof Node\Expr\PropertyFetch => Type::mixed(),
            $expr instanceof Node\Expr\ArrayDimFetch => Type::mixed(),
            $expr instanceof Node\Expr\Ternary => $this->inferTypeFromTernary($expr),
            $expr instanceof Node\Expr\BinaryOp => $this->inferTypeFromBinaryOp($expr),
            default => Type::mixed()
        };
    }

    protected function inferTypeFromConstant(Node\Expr\ConstFetch $const): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        return match (strtolower($const->name->toString())) {
            'true', 'false' => Type::bool(),
            'null' => Type::null(),
            default => Type::mixed()
        };
    }

    protected function inferTypeFromFunctionCall(Node\Expr\FuncCall $funcCall): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        if ($funcCall->name instanceof Node\Name) {
            return match ($funcCall->name->toString()) {
                'view' => Type::string('Illuminate\\View\\View'),
                'redirect' => Type::string('Illuminate\\Http\\RedirectResponse'),
                'response' => Type::string('Illuminate\\Http\\Response'),
                'json' => Type::string('Illuminate\\Http\\JsonResponse'),
                'count', 'sizeof', 'strlen', 'rand', 'mt_rand' => Type::int(),
                'array_merge', 'array_filter', 'array_map', 'compact' => Type::array([]),
                'json_encode', 'serialize', 'md5', 'sha1' => Type::string(),
                'json_decode', 'unserialize' => Type::mixed(),
                default => Type::mixed()
            };
        }

        return Type::mixed();
    }

    protected function inferTypeFromMethodCall(Node\Expr\MethodCall $methodCall): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        if ($methodCall->name instanceof Node\Identifier) {
            $methodName = $methodCall->name->toString();

            // Common Laravel patterns
            return match ($methodName) {
                'view' => Type::string('Illuminate\\View\\View'),
                'redirect' => Type::string('Illuminate\\Http\\RedirectResponse'),
                'json' => Type::string('Illuminate\\Http\\JsonResponse'),
                'paginate' => Type::string('Illuminate\\Pagination\\LengthAwarePaginator'),
                'get', 'first', 'find' => Type::mixed(), // Model instance or null
                'all', 'where' => Type::mixed(), // Collection
                default => Type::mixed()
            };
        }

        return Type::mixed();
    }

    protected function inferTypeFromStaticCall(Node\Expr\StaticCall $staticCall): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        // Handle static method calls like Model::find(), etc.
        return Type::mixed();
    }

    protected function inferTypeFromNew(Node\Expr\New_ $new): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        if ($new->class instanceof Node\Name) {
            return Type::string($new->class->toString());
        }

        return Type::mixed();
    }

    protected function inferTypeFromTernary(Node\Expr\Ternary $ternary): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        // For ternary operators, we could return a union of both possible types
        $ifType = $ternary->if ? $this->inferTypeFromExpression($ternary->if) : Type::null();
        $elseType = $this->inferTypeFromExpression($ternary->else);

        return Type::union($ifType, $elseType);
    }

    protected function inferTypeFromBinaryOp(Node\Expr\BinaryOp $binaryOp): \Laravel\StaticAnalyzer\Types\Contracts\Type
    {
        return match (true) {
            $binaryOp instanceof Node\Expr\BinaryOp\Concat => Type::string(),
            $binaryOp instanceof Node\Expr\BinaryOp\Plus,
            $binaryOp instanceof Node\Expr\BinaryOp\Minus,
            $binaryOp instanceof Node\Expr\BinaryOp\Mul,
            $binaryOp instanceof Node\Expr\BinaryOp\Div => Type::mixed(), // Could be int or float
            $binaryOp instanceof Node\Expr\BinaryOp\Equal,
            $binaryOp instanceof Node\Expr\BinaryOp\NotEqual,
            $binaryOp instanceof Node\Expr\BinaryOp\Greater,
            $binaryOp instanceof Node\Expr\BinaryOp\GreaterOrEqual,
            $binaryOp instanceof Node\Expr\BinaryOp\Smaller,
            $binaryOp instanceof Node\Expr\BinaryOp\SmallerOrEqual => Type::bool(),
            default => Type::mixed()
        };
    }
}
