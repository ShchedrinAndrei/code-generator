<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Dictionary;

use Nette\PhpGenerator\ClassLike;

final readonly class PhpSyntax
{
    public const NS_SEPARATOR = '\\';
    public const EMPTY_ARRAY = 'empty_array';

    public static function combineNsParts(?string ...$parts): string
    {
        return preg_replace(
            '/\\+/',
            self::NS_SEPARATOR,
            implode(
                self::NS_SEPARATOR,
                array_filter($parts)
            )
        );
    }

    public static function getClassLikeFullName(ClassLike $classLike): string
    {
        /** @noinspection PhpDeprecationInspection */
        return self::combineNsParts($classLike->getNamespace()?->getName(), $classLike->getName());
    }

    public static function extractClassName(string $ns): string
    {
        return substr(strrchr($ns, self::NS_SEPARATOR), 1);
    }

    public static function scalarToRow(mixed $value, bool $wrapArray = false): string
    {
        $value = match (true) {
            is_string($value) => $value === PhpSyntax::EMPTY_ARRAY ? '' : "'$value'",
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            default => $value
        };

        return sprintf(
            '%s%s%s',
            $wrapArray ? '[' : '',
            $value,
            $wrapArray ? ']' : ''
        );
    }
}
