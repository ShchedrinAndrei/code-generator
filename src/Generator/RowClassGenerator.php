<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Schema;
use Closure;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Dictionary\Extensions;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Model\ParamStructureElement;
use Shchandrei\CodeGenerator\Model\StructureType;
use Shchandrei\CodeGenerator\Service\SchemaManipulations;

readonly class RowClassGenerator
{
    /** @param Schema[] $properties */
    public function generateStructure(array $properties): array
    {
        $rowProperties = [];
        foreach ($properties as $propertyName => $property) {
            if ($property->example) {
                $rowProperties[$propertyName] = new ParamStructureElement(
                    StructureType::TYPE_SCALAR,
                    $property->example
                );
                continue;
            }

            if ($property->type === 'array') {
                if ($property->items->type === 'object') {
                    $rowProperties[$propertyName] = new ParamStructureElement(
                        StructureType::TYPE_ARR_OF_OBJECT,
                        $this->generateStructure($property->items->properties),
                        SchemaManipulations::getSchemaName($property->items),
                        $property->items->getExtensions()[Extensions::FEATURE] ?? null,
                    );
                } else {
                    $rowProperties[$propertyName] = new ParamStructureElement(
                        StructureType::TYPE_ARRAY,
                        array_key_exists(Extensions::JSON_PARAMS, $property->getExtensions())
                            ? null
                            : $property->items->example
                    );
                }
            }

            if ($property->type === 'object') {
                $rowProperties[$propertyName] = new ParamStructureElement(
                    StructureType::TYPE_OBJECT,
                    $this->generateStructure($property->properties),
                    SchemaManipulations::getSchemaName($property),
                    $property->getExtensions()[Extensions::FEATURE] ?? null
                );
            }
        }

        return $rowProperties;
    }

    /** @param array<string, ParamStructureElement> $structure */
    public function unpackRowClassProperty(
        Method $method,
        PhpNamespace $namespace,
        Closure $getFullDtoName,
        string $feature,
        array $structure,
        string $rowTabs
    ): void {
        foreach ($structure as $propName => $prop) {
            switch ($prop->type) {
                case StructureType::TYPE_SCALAR:
                    $method->addBody(
                        sprintf('%s%s: %s,', $rowTabs, $propName, PhpSyntax::scalarToRow($prop->element))
                    );
                    break;
                case StructureType::TYPE_ARRAY:
                    $value = ($prop->element === null) ? '[]' : PhpSyntax::scalarToRow($prop->element, true);
                    $method->addBody(
                        sprintf('%s%s: %s,', $rowTabs, $propName, $value)
                    );
                    break;
                case StructureType::TYPE_OBJECT:
                    $dtoFullName = $getFullDtoName($prop->forceFeature ?? $feature, $prop->objectName);
                    $dtoName = PhpSyntax::extractClassName($dtoFullName);
                    $namespace->removeUse($dtoFullName);
                    $namespace->addUse($dtoFullName);
                    $method->addBody(sprintf('%s%s: new %s(', $rowTabs, $propName, $dtoName));
                    $this->unpackRowClassProperty(
                        $method,
                        $namespace,
                        $getFullDtoName,
                        $prop->forceFeature ?? $feature,
                        $prop->element,
                        $rowTabs . "\t"
                    );
                    $method->addBody(sprintf('%s),', $rowTabs));
                    break;
                case StructureType::TYPE_ARR_OF_OBJECT:
                    $dtoFullName = $getFullDtoName($prop->forceFeature ?? $feature, $prop->objectName);
                    $dtoName = PhpSyntax::extractClassName($dtoFullName);
                    $namespace->removeUse($dtoFullName);
                    $namespace->addUse($dtoFullName);
                    $method->addBody(sprintf('%s%s: [new %s(', $rowTabs, $propName, $dtoName));
                    $this->unpackRowClassProperty(
                        $method,
                        $namespace,
                        $getFullDtoName,
                        $prop->forceFeature ?? $feature,
                        $prop->element,
                        $rowTabs . "\t"
                    );
                    $method->addBody(sprintf('%s)],', $rowTabs));
            }
        }
    }
}
