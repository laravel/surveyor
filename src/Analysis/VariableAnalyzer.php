<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Result\VariableTracker;
use Laravel\StaticAnalyzer\Types\Type;
use Laravel\StaticAnalyzer\Parser\Parser;
use PhpParser\Node;

class VariableAnalyzer extends AbstractResolver
{
    protected VariableTracker $tracker;

    public function analyze(Node\Stmt\ClassMethod $methodNode): VariableTracker
    {
        $this->tracker = new VariableTracker();

        // Add method parameters as initial variables
        foreach ($methodNode->params as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $type = $param->type ? $this->from($param->type) : Type::mixed();
                $this->tracker->addVariable($param->var->name, $type, $param->getStartLine(), 'main');
            } else {
                dd('not a variable??', $param);
            }
        }

        $this->processStatements($methodNode->stmts ?? [], 'main');

        return $this->tracker;
    }

    protected function processStatements(array $statements, string $pathId): void
    {
        foreach ($statements as $stmt) {
            $this->processStatement($stmt, $pathId);
        }
    }

    protected function processStatement(Node $stmt, string $pathId): void
    {
        switch (true) {
            case $stmt instanceof Node\Expr\Assign:
                $this->processAssignment($stmt, $pathId);
                break;

            case $stmt instanceof Node\Stmt\If_:
                $this->processIfStatement($stmt, $pathId);
                break;

            case $stmt instanceof Node\Stmt\While_:
            case $stmt instanceof Node\Stmt\For_:
            case $stmt instanceof Node\Stmt\Foreach_:
                $this->processLoopStatement($stmt, $pathId);
                break;

            case $stmt instanceof Node\Stmt\Return_:
                $this->processReturnStatement($stmt, $pathId);
                break;

            case $stmt instanceof Node\Stmt\Expression:
                // Handle expression statements that might contain assignments
                $this->processStatement($stmt->expr, $pathId);
                break;

            case $stmt instanceof Node\Expr\AssignOp:
                $this->processAssignmentOperation($stmt, $pathId);
                break;

            case $stmt instanceof Node\Expr\FuncCall:
                $this->processFunctionCall($stmt, $pathId);
                break;

            default:
                // Handle any nested statements
                if (property_exists($stmt, 'stmts') && is_array($stmt->stmts)) {
                    $this->processStatements($stmt->stmts, $pathId);
                }
        }
    }

    protected function processAssignment(Node\Expr\Assign $assignment, string $pathId): void
    {
        if (!$assignment->var instanceof Node\Expr\Variable) {
            dd('not a variable??', $assignment);
        }

        if (!is_string($assignment->var->name)) {
            dd('variable but not a string??', $assignment);
        }

        $this->tracker->add(
            $assignment->var->name,
            $this->from($assignment->expr),
            $assignment->getStartLine(),
        );

        $this->tracker->addVariable(
            $assignment->var->name,
            $this->from($assignment->expr),
            $assignment->getStartLine(),
            $pathId
        );
    }

    protected function processAssignmentOperation(Node\Expr\AssignOp $assignment, string $pathId): void
    {
        if ($assignment->var instanceof Node\Expr\Variable && is_string($assignment->var->name)) {
            // For operations like +=, -=, etc., we keep the existing type but update the line
            $existingStates = $this->tracker->getVariableAtLine($assignment->var->name, $assignment->getStartLine());
            $type = !empty($existingStates) ? $existingStates[0]->type : Type::mixed();

            $this->tracker->addVariable(
                $assignment->var->name,
                $type,
                $assignment->getStartLine(),
                $pathId
            );
        }
    }

    protected function processIfStatement(Node\Stmt\If_ $ifStmt, string $pathId): void
    {
        $printer = app(Parser::class)->printer();

        $conditionString = $printer->prettyPrintExpr($ifStmt->cond);

        // Fork path for if branch
        $ifPathId = $this->tracker->forkPath($conditionString, $pathId, $ifStmt->getStartLine(), $ifStmt->getEndLine());

        $ifChanges = [];
        $elseChanges = [];

        // dd($ifPathId, $this->tracker);

        // Process if body
        if ($ifStmt->stmts) {
            $this->tracker->startSnapshot($ifStmt->getStartLine());
            $this->processStatements($ifStmt->stmts, $ifPathId);
            $changed = $this->tracker->endSnapshot($ifStmt->getStartLine());
            $ifChanges[] = $changed;
        }

        // Process elseif branches
        $currentElsePathId = $pathId;
        foreach ($ifStmt->elseifs as $elseif) {
            $conditionElseIfString = $printer->prettyPrintExpr($elseif->cond);
            $elseifPathId = $this->tracker->forkPath($conditionElseIfString, $currentElsePathId, $elseif->getStartLine(), $elseif->getEndLine());
            if ($elseif->stmts) {
                $this->tracker->startSnapshot($elseif->getStartLine());
                $this->processStatements($elseif->stmts, $elseifPathId);
                $changed = $this->tracker->endSnapshot($elseif->getStartLine());
                $ifChanges[] = $changed;
            }
            $currentElsePathId = $this->tracker->forkPath($conditionElseIfString . '-false', $currentElsePathId, $elseif->getStartLine(), $elseif->getEndLine());
        }

        // Process else branch
        if ($ifStmt->else) {
            $elsePathId = $conditionString . '-false';
            if ($ifStmt->else->stmts) {
                $this->tracker->startSnapshot($ifStmt->else->getStartLine());
                $this->processStatements($ifStmt->else->stmts, $elsePathId);
                $changed = $this->tracker->endSnapshot($ifStmt->else->getStartLine());
                $elseChanges = $changed;
            }
        }

        $finalIfChanges = [];

        foreach ($ifChanges as $changes) {
            foreach ($changes as $name => $changes) {
                $finalIfChanges[$name] ??= [];
                $finalIfChanges[$name] = array_merge($finalIfChanges[$name], $changes);
            }
        }

        foreach ($finalIfChanges as $name => $changes) {
            $elseVariable = $elseChanges[$name] ?? null;
            $types = array_map(fn($change) => $change['type'], $changes);

            if ($elseVariable) {
                $types[] = $elseVariable[0]['type'];
            } else {
                array_unshift($types, $this->tracker->getAtLine($name, $ifStmt->getStartLine() - 1)['type']);
            }

            $this->tracker->add($name, Type::union(...$types), $ifStmt->getEndLine());
        }

        // dd($this->tracker->getVariables());
    }

    protected function processLoopStatement(Node $loopStmt, string $pathId): void
    {
        $loopType = match (true) {
            $loopStmt instanceof Node\Stmt\While_ => 'while',
            $loopStmt instanceof Node\Stmt\For_ => 'for',
            $loopStmt instanceof Node\Stmt\Foreach_ => 'foreach',
            default => 'loop'
        };

        // Fork path for loop body
        $loopPathId = $this->tracker->forkPath($loopType . '-body', $pathId);

        // Handle foreach variable assignments
        if ($loopStmt instanceof Node\Stmt\Foreach_) {
            if ($loopStmt->valueVar instanceof Node\Expr\Variable && is_string($loopStmt->valueVar->name)) {
                $this->tracker->addVariable(
                    $loopStmt->valueVar->name,
                    Type::mixed(), // Could be improved with better type inference
                    $loopStmt->valueVar->getStartLine(),
                    $loopPathId
                );
            }

            if ($loopStmt->keyVar instanceof Node\Expr\Variable && is_string($loopStmt->keyVar->name)) {
                $this->tracker->addVariable(
                    $loopStmt->keyVar->name,
                    Type::mixed(),
                    $loopStmt->keyVar->getStartLine(),
                    $loopPathId
                );
            }
        }

        // Process loop body
        if (property_exists($loopStmt, 'stmts') && $loopStmt->stmts) {
            $this->processStatements($loopStmt->stmts, $loopPathId);
        }
    }

    protected function processFunctionCall(Node\Expr\FuncCall $funcCall, string $pathId): void
    {
        // Handle function calls that might affect variables (like compact())
        if ($funcCall->name instanceof Node\Name && $funcCall->name->toString() === 'compact') {
            // For compact() calls, we could track which variables are being used
            // but for now we'll just process the arguments
            foreach ($funcCall->args as $arg) {
                if ($arg->value) {
                    $this->processStatement($arg->value, $pathId);
                }
            }
        }
    }

    protected function processReturnStatement(Node\Stmt\Return_ $returnStmt, string $pathId): void
    {
        // Terminate the current path when we hit a return statement
        $this->tracker->terminatePath($pathId, $returnStmt->getStartLine());
    }
}
