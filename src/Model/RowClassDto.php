<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

final readonly class RowClassDto
{
    /** @param array<string, ParamStructureElement> $structure */
    public function __construct(
        public string $feature,
        public string $className,
        public array $structure,
        public bool $isWithoutParams = false
    ) {
    }
}
