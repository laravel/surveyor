<?php

namespace Laravel\Surveyor\Analysis;

use Exception;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Result\StateTracker;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\TemplateTagType;
use Laravel\Surveyor\Types\Type as SurveyorType;
use PhpParser\Comment\Doc;

class Scope
{
    protected ?string $entityName = null;

    protected ?string $methodName = null;

    protected StateTracker $stateTracker;

    protected array $children = [];

    protected array $uses = [];

    protected ?string $namespace = null;

    protected array $traits = [];

    protected array $constants = [];

    protected bool $analyzingCondition = false;

    protected bool $analyzingConditionPaused = false;

    protected array $returnTypes = [];

    protected string $path;

    protected EntityType $entityType;

    protected array $cases = [];

    protected $pendingDocBlock = null;

    /**
     * @var PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode[]
     */
    protected array $templateTags = [];

    public function __construct(protected ?Scope $parent = null)
    {
        $this->stateTracker = new StateTracker;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function fullPath(): ?string
    {
        if (! isset($this->path)) {
            if ($this->parent) {
                return $this->parent->fullPath();
            }

            return null;
        }

        return $this->path;
    }

    public function path(): ?string
    {
        if (isset($this->path)) {
            return str_replace($_ENV['HOME'], '~', $this->path);
        }

        if ($this->parent) {
            return $this->parent->path();
        }

        return null;
    }

    public function addConstant(string $constant, Type $type): void
    {
        $this->constants[$constant] = $type;
    }

    public function children()
    {
        return $this->children;
    }

    public function getConstant(string $constant): ?Type
    {
        if (! array_key_exists($constant, $this->constants)) {
            if ($this->parent) {
                return $this->parent->getConstant($constant);
            }

            if (str_ends_with($constant, '*')) {
                return SurveyorType::mixed();
            }

            return SurveyorType::mixed();
        }

        return $this->constants[$constant] ?? throw new Exception('Constant '.$constant.' not found');
    }

    public function setPendingDocBlock(Doc $docBlock): void
    {
        $this->pendingDocBlock = $docBlock;
    }

    public function getPendingDocBlock(): ?string
    {
        $block = $this->pendingDocBlock;
        $this->pendingDocBlock = null;

        return $block;
    }

    public function addReturnType(Type $returnType, int $lineNumber): void
    {
        $this->returnTypes[] = [
            'type' => $returnType,
            'lineNumber' => $lineNumber,
        ];
    }

    public function returnTypes(): array
    {
        return $this->returnTypes;
    }

    public function setEntityType(EntityType $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function setEntityName(string $entityName, bool $quietly = false): void
    {
        $this->entityName = $entityName;
        $this->stateTracker->setThis($entityName);

        if (! $quietly) {
            Debug::log('🔬 Scope: '.$entityName, level: 2);
        }
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function newChildScope(): self
    {
        $instance = new self($this);

        if ($this->entityName) {
            $instance->setEntityName($this->entityName, true);
        }

        if ($this->methodName) {
            $instance->setMethodName($this->methodName, true);
        }

        if ($this->namespace) {
            $instance->setNamespace($this->namespace);
        }

        foreach ($this->state()->properties()->variables() as $name => $properties) {
            foreach ($properties as $property) {
                $instance->state()->properties()->addManually(
                    $name,
                    $property->type(),
                    $property->startLine(),
                    $property->startTokenPos(),
                    $property->endLine(),
                    $property->endTokenPos(),
                    $property->terminatedAt(),
                );
            }
        }

        foreach ($this->state()->variables()->variables() as $name => $properties) {
            foreach ($properties as $property) {
                $instance->state()->variables()->addManually(
                    $name,
                    $property->type(),
                    $property->startLine(),
                    $property->startTokenPos(),
                    $property->endLine(),
                    $property->endTokenPos(),
                    $property->terminatedAt(),
                );
            }
        }

        $this->children[] = $instance;

        return $instance;
    }

    public function addTrait(string $trait): void
    {
        $this->traits[] = $trait;
    }

    public function addUse(string $use, ?string $alias = null): void
    {
        $this->uses[$alias ?? $use] = $use;
    }

    public function getUse(string $candidate): string
    {
        if (in_array($candidate, ['static', 'self'])) {
            return $this->entityName;
        }

        foreach ($this->uses as $alias => $use) {
            if ($candidate === $alias || str_ends_with($alias, '\\'.$candidate)) {
                return $use;
            }
        }

        if ($this->parent) {
            return $this->parent->getUse($candidate);
        }

        if ($this->namespace && Util::isClassOrInterface($this->namespace.'\\'.$candidate)) {
            return $this->namespace.'\\'.$candidate;
        }

        return $candidate;
    }

    public function setMethodName(string $methodName, bool $quietly = false): void
    {
        $this->methodName = $methodName;

        if (! $quietly) {
            Debug::log("🔬 Scope: {$this->entityName}::{$methodName}", level: 2);
        }
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    public function addCase(string $case, ?Type $type): void
    {
        $this->constants[$case] = $type;
    }

    public function entityName(): ?string
    {
        return $this->entityName;
    }

    public function methodName(): ?string
    {
        return $this->methodName;
    }

    public function state()
    {
        return $this->stateTracker;
    }

    public function methodScope(string $methodName): Scope
    {
        return collect($this->children)->first(fn ($child) => $child->methodName() === $methodName);
    }

    public function startConditionAnalysis($quiet = false): void
    {
        if (! $quiet) {
            Debug::log('🟢 Starting condition analysis: '.$this->path(), level: 3);
        }

        $this->analyzingCondition = true;
    }

    public function endConditionAnalysis($quiet = false): void
    {
        if (! $quiet) {
            Debug::log('🔴 Ending condition analysis: '.$this->path(), level: 3);
        }

        $this->analyzingCondition = false;
    }

    public function pauseConditionAnalysis(): void
    {
        if ($this->analyzingConditionPaused || ! $this->analyzingCondition) {
            return;
        }

        Debug::log('🟡 Pausing condition analysis: '.$this->path(), level: 3);

        $this->analyzingConditionPaused = true;
        $this->endConditionAnalysis(true);
    }

    public function resumeConditionAnalysis(): void
    {
        if (! $this->analyzingConditionPaused) {
            return;
        }

        Debug::log('🟠 Resuming condition analysis: '.$this->path(), level: 3);

        $this->analyzingConditionPaused = false;
        $this->startConditionAnalysis(true);
    }

    public function analyzingConditionPaused(): bool
    {
        return $this->analyzingConditionPaused;
    }

    public function isAnalyzingCondition(): bool
    {
        return $this->analyzingCondition;
    }

    public function setTemplateTags(array $templateTags): void
    {
        $this->templateTags = $templateTags;
    }

    public function getTemplateTags(): array
    {
        if (count($this->templateTags) > 0) {
            return $this->templateTags;
        }

        if ($this->parent) {
            return $this->parent->getTemplateTags();
        }

        return [];
    }

    public function getTemplateTag(string $name): ?TemplateTagType
    {
        return collect($this->templateTags)->first(fn ($tag) => $tag->name === $name);
    }
}
