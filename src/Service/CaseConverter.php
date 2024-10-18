<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Service;

use Jawira\CaseConverter\CaseConverterInterface;

final readonly class CaseConverter
{
    public function __construct(
        private CaseConverterInterface $caseConverter
    ) {
    }

    public function toPascal(string ...$parts): string
    {
        $result = '';
        foreach ($parts as $part) {
            $result .= $this->caseConverter->convert($part)->toPascal();
        }

        return $result;
    }

    public function toCamel(string ...$parts): string
    {
        $result = '';
        foreach ($parts as $part) {
            $result .= $this->caseConverter->convert($part)->toCamel();
        }

        return $result;
    }

    public function toSnake(string ...$parts): string
    {
        $result = '';
        foreach ($parts as $part) {
            $result .= $this->caseConverter->convert($part)->toSnake();
        }

        return $result;
    }
}
