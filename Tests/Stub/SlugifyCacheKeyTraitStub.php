<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Stub;

use mteu\Monitoring\Trait\SlugifyCacheKeyTrait;

final readonly class SlugifyCacheKeyTraitStub
{
    use SlugifyCacheKeyTrait;

    public function stubSlugifyString(string $value): string
    {
        return $this->slugifyString($value);
    }
}
