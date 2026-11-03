<?php

namespace App\Spreading;

class Conditional
{
    public bool $withSettings = false;

    public function toArray(): array
    {
        return [
            'id' => 1,
            ...$this->whenSettings(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function whenSettings(): array
    {
        if (! $this->withSettings) {
            return [];
        }

        return [
            'php_version' => '8.4',
            'node_version' => $this->nodeVersion(),
        ];
    }

    protected function nodeVersion()
    {
        return config('app.node_version');
    }
}
