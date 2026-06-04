<?php

namespace App\Domains\Qdvs\Support;

use App\Domains\Qdvs\Contracts\QdvsSpec;
use InvalidArgumentException;

class SpecRegistry
{
    /** @return array<string, QdvsSpec> */
    public function all(): array
    {
        $out = [];
        foreach (config('qdvs.specs') as $class) {
            $spec = app($class);
            $out[$spec->key()] = $spec;
        }

        return $out;
    }

    public function get(string $key): QdvsSpec
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unbekannte QDVS-Spec: {$key}");
    }

    public function default(): QdvsSpec
    {
        return $this->get(config('qdvs.default_spec'));
    }
}
