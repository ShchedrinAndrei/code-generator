<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

final readonly class ParamStructureElement
{
    public function __construct(
        public StructureType $type,
        public mixed $element,
        public ?string $objectName = null,
        public ?string $forceFeature = null
    ) {
    }
}
