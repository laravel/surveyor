<?php

namespace App\Support;

use App\Attributes\ConditionalIgnore;
use App\Attributes\Ignore;
use App\Attributes\Unrelated;

class MarkedByReflection
{
    #[Ignore]
    public string $marked = 'value';

    #[Unrelated]
    public string $unrelated = 'value';

    public string $plain = 'value';

    /** @ignore */
    public string $taggedInDocBlock = 'value';

    #[Ignore(true)]
    public string $argumentOnPlainMarker = 'value';

    #[ConditionalIgnore(unless: 'features.fake')]
    public string $keptWhileFake = 'value';

    #[ConditionalIgnore(when: 'features.retired')]
    public string $hiddenWhileRetired = 'value';

    #[Ignore]
    public function markedMethod(): string
    {
        return 'value';
    }
}
