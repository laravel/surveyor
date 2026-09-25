<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin Tag */
class ConditionalDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'when' => $this->when($request->boolean('details'), fn (): string => 'visible', 0),
            'unless' => $this->unless($request->boolean('details'), 'visible'),
            'nullable_default' => $this->when(true, 'visible', null),
            'missing_default' => $this->when(true, 'visible', new MissingValue),
            'named_default' => $this->when(default: 0, value: 'visible', condition: true),
            'has' => $this->whenHas('label', fn (): int => 1, 0),
            'appended' => $this->whenAppended('label'),
            'not_null' => $this->whenNotNull($this->label),
            'null_value' => $this->whenNull($this->label),
            'null_with_default' => $this->whenNull($this->label, 'fallback'),
            'count' => $this->whenCounted('tags', fn (): string => 'many', 'none'),
            'aggregate' => $this->whenAggregated('tags', 'id', 'max', fn (): int => 1, 0),
            'named_aggregate' => $this->whenAggregated(default: 'none', value: fn (): int => 1, aggregate: 'max', column: 'id', relationship: 'tags'),
            'exists' => $this->whenExistsLoaded('tags'),
            'pivot' => $this->whenPivotLoaded('tag_user', fn (): int => 1, 'none'),
            'pivot_as' => $this->whenPivotLoadedAs('subscription', 'tag_user', fn (): int => 1, 'none'),
            'nested' => PlainLabelResource::make($this->when(true, new Tag)),
            'nested_new' => new PlainLabelResource($this->when(true, new Tag)),
            'nested_collection' => PlainLabelResource::collection($this->whenLoaded('label')),
            $this->merge(['always' => true]),
            $this->mergeWhen($request->boolean('details'), fn (): array => ['secret' => 'value']),
            $this->mergeUnless($request->boolean('details'), ['unless_merged' => 1]),
            $this->mergeWhen(true, ['common' => 1, 'primary' => true], ['common' => 'none', 'fallback' => true]),
            $this->merge(['still_optional' => $this->when(true, 'value')]),
            $this->mergeWhen(true, ['missing_merge' => 1], new MissingValue),
            $this->mergeWhen(true, ['null_merge' => 1], null),
            $this->mergeUnless(
                default: function (): array {
                    return ['closure_common' => 'fallback', 'closure_fallback' => true];
                },
                value: function (): array {
                    return ['closure_common' => 1, 'closure_primary' => true];
                },
                condition: true,
            ),
            'ordinary_array' => ['kept' => true],
        ];
    }
}
