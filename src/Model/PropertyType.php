<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

class PropertyType
{
    public function __construct(
        public bool $isNullable,
        public bool $isRequired,
        public ?string $scalarType = null,
        public ?string $genericString = null,
        public ?string $fullNamespace = null,
        public bool $hasDefault = false,
        public mixed $default = null,
        public bool $needSubModelGenerate = false,
    ) {
    }
}
