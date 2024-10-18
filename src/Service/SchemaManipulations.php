<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Service;

use cebe\openapi\spec\Schema;

use function array_keys;
use function is_array;

final readonly class SchemaManipulations
{
    public static function flattenSchema(Schema $schema): Schema
    {
        $allOf = $schema->allOf ?? [];
        if (count($allOf) > 0) {
            unset($schema->allOf);
            foreach ($allOf as $parentSchema) {
                $props = array_keys((array) $parentSchema->getSerializableData());
                foreach ($props as $prop) {
                    if (!isset($schema->$prop)) {
                        $schema->$prop = $parentSchema->$prop;
                    } elseif (is_array($parentSchema->$prop) && is_array($schema->$prop)) {
                        $schema->$prop = array_merge(
                            $schema->$prop,
                            array_diff_key($parentSchema->$prop, $schema->$prop)
                        );
                    }
                }
            }
        }

        if (count($schema->properties ?? []) > 0) {
            $properties = [];
            foreach ($schema->properties as $name => $property) {
                $properties[$name] = self::flattenSchema($property);
            }

            $schema->properties = $properties;
        }

        if ($schema->items) {
            $schema->items = self::flattenSchema($schema->items);
        }

        return $schema;
    }

    public static function getSchemaName(Schema $schema): string
    {
        $el = explode(
            '/',
            $schema->getDocumentPosition()->getPointer()
        );

        return $el[array_key_last($el)];
    }
}
