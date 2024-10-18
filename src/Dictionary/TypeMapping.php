<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Dictionary;

use cebe\openapi\spec\Type;

final class TypeMapping
{
    public static function map(?string $type): ?string
    {
        return match ($type) {
            Type::BOOLEAN => 'bool',
            Type::NUMBER => 'float',
            Type::INTEGER => 'int',
            Type::STRING => 'string',
            Type::ARRAY => 'array',
            default => null
        };
    }
}
