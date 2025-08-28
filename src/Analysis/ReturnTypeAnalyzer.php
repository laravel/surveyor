<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Illuminate\Support\Collection;
use Illuminate\View\View as ViewView;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\ArrayType;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Entities\View;
use Laravel\StaticAnalyzer\Types\Type;
use Laravel\StaticAnalyzer\Types\UnionType;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;

class ReturnTypeAnalyzer extends AbstractResolver
{
    protected array $returnTypes = [];

    public function analyze(Node\Stmt\ClassMethod $methodNode, Scope $scope): array
    {
        $this->scope = $scope;
        $this->returnTypes = [];

        $this->processStatements($methodNode->stmts ?? []);

        if ($methodNode->returnType) {
            $this->addMethodNativeReturnType($methodNode);
        }

        return collect($this->returnTypes)
            ->groupBy(fn ($type) => $type::class)
            ->map(fn ($group, $class) => $this->collapseReturnTypes($group, $class))
            ->values()
            ->flatten()
            ->all();
    }

    /**
     * Add the native return type only if we don't already have something more specific.
     */
    protected function addMethodNativeReturnType(Node\Stmt\ClassMethod $methodNode): void
    {
        $type = $this->from($methodNode->returnType);

        if (! $type instanceof ClassType) {
            $this->returnTypes[] = $type;

            return;
        }

        $hasView = collect($this->returnTypes)->first(fn ($type) => $type instanceof View);

        if ($type->value === ViewView::class && $hasView) {
            return;
        }

        $this->returnTypes[] = $type;
    }

    protected function collapseReturnTypes(Collection $returnTypes, string $class)
    {
        switch ($class) {
            case View::class:
                return $this->collapseViewReturnTypes($returnTypes);
        }

        return $returnTypes;
    }

    protected function collapseViewReturnTypes(Collection $returnTypes)
    {
        return $returnTypes->groupBy(fn ($type) => $type->name)->map(function ($group) {
            if ($group->count() === 1) {
                return $group->first();
            }

            $dataKeys = $group->map(fn ($type) => array_keys($type->data));
            $requiredKeys = array_values(array_intersect(...$dataKeys->all()));

            $newData = [];

            foreach ($group as $view) {
                foreach ($view->data as $key => $value) {
                    $value->required(in_array($key, $requiredKeys));

                    $newData[$key] ??= [];
                    $newData[$key][] = $value;
                }
            }

            foreach ($newData as $key => $value) {
                $newData[$key] = Type::union(...$value);
            }

            return View::from(new ClassType($group->first()->value), $group->first()->name, $newData);
        });
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
                $this->processExpression($stmt->expr);
                break;

            default:
                $this->processGenericStatement($stmt);
        }
    }

    protected function processReturnStatement(Node\Stmt\Return_ $returnStmt): void
    {
        if ($returnStmt->expr) {
            $returnType = $this->from($returnStmt->expr);
        } else {
            $returnType = Type::void();
        }

        $this->returnTypes[] = $this->remapReturnType($returnType, $returnStmt);
    }

    protected function remapReturnType(TypeContract $returnType, Node\Stmt\Return_ $returnStmt): TypeContract
    {
        if (! $returnType instanceof ClassType) {
            return $returnType;
        }

        switch ($returnType->value) {
            case 'Illuminate\Contracts\View\View':
            case 'Illuminate\View\View':
                return $this->mapToView($returnType, $returnStmt);
        }

        // dd($returnType);

        return $returnType;
    }

    protected function mapToView(ClassType $returnType, Node\Stmt\Return_ $returnStmt): View
    {
        if ($returnStmt->expr instanceof CallLike) {
            $args = $returnStmt->expr->getArgs();
            $args = collect($args)->map(fn (Arg $arg) => $this->from($arg->value))->toArray();
        } else {
            dd('not call like', $returnStmt->expr);
        }

        $viewName = $args[0]->value;
        $possibleData = $args[1] ?? null;

        if ($possibleData instanceof UnionType && collect($possibleData->types)->every(fn ($t) => $t instanceof ArrayType)) {
            $allKeys = collect($possibleData->types)->map(fn ($t) => array_keys($t->value));
            $requiredKeys = array_values(array_intersect(...$allKeys->all()));

            $data = [];

            foreach ($possibleData->types as $type) {
                foreach ($type->value as $key => $value) {
                    $value->required(in_array($key, $requiredKeys));

                    $data[$key] ??= [];
                    $data[$key][] = $value;
                }
            }

            foreach ($data as $key => $value) {
                $data[$key] = Type::union(...$value);
            }
        } else {
            $data = $possibleData?->value ?? [];
        }

        return View::from($returnType, $viewName, $data);
    }

    protected function processIfStatement(Node\Stmt\If_ $ifStmt): void
    {
        if ($ifStmt->stmts) {
            $this->processStatements($ifStmt->stmts);
        }

        foreach ($ifStmt->elseifs as $elseif) {
            if ($elseif->stmts) {
                $this->processStatements($elseif->stmts);
            }
        }

        if ($ifStmt->else && $ifStmt->else->stmts) {
            $this->processStatements($ifStmt->else->stmts);
        }
    }

    protected function processLoopStatement(Node $loopStmt): void
    {
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
        $this->processStatements($tryCatchStmt->stmts);

        foreach ($tryCatchStmt->catches as $catch) {
            $this->processStatements($catch->stmts);
        }

        if ($tryCatchStmt->finally) {
            $this->processStatements($tryCatchStmt->finally->stmts);
        }
    }

    protected function processExpression(Node\Expr $expr): void
    {
        if ($expr instanceof Node\Expr\Closure) {
            $this->processStatements($expr->stmts);
        } elseif ($expr instanceof Node\Expr\ArrowFunction) {
            $this->returnTypes[] = $this->from($expr->expr);
        }
    }

    protected function processGenericStatement(Node $stmt): void
    {
        if ($stmt instanceof Node\Stmt\Nop) {
            return;
        }

        // TODO: Decide if we need to deal with this or not
        return;

        dd($stmt, 'generic statement!');
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
}
