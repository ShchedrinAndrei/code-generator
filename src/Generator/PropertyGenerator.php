<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Closure;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Builder\AttributeBuilder;
use Shchandrei\CodeGenerator\Dictionary\Extensions;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Dictionary\PlatformClasses;
use Shchandrei\CodeGenerator\Dictionary\SymfonyClasses;
use Shchandrei\CodeGenerator\Dictionary\TypeMapping;
use Shchandrei\CodeGenerator\Model\ModelMethods;
use Shchandrei\CodeGenerator\Model\PropertyType;

readonly class PropertyGenerator
{
    public function __construct(
        private AttributeBuilder $attributeBuilder
    ) {
    }

    public function generateListParams(
        PhpNamespace $namespace,
        ClassType $class
    ): void {
        $namespace->addUse(SymfonyClasses::CONSTRAINTS_NS)
            ->addUse(PlatformClasses::AUTO_DESERIALIZABLE_INTERFACE);
        $class->setImplements([PlatformClasses::AUTO_DESERIALIZABLE_INTERFACE]);

        $class->addProperty('listParams')
            ->setType('array')
            ->setPrivate()
            ->setValue([])
            ->setComment('@var string[]');

        $class->addMethod('setListParams')
            ->setReturnType('void')
            ->setComment('@param string[] $listParams')
            ->addBody('$this->listParams = $listParams;')
            ->addParameter('listParams')
            ->setType('array');

        $class->addMethod('getListParams')
            ->setReturnType('array')
            ->addBody('return $this->listParams;')
            ->setComment('@return string[]');
    }

    public function generateProperty(
        PhpNamespace $namespace,
        Reference | Schema $property,
        string $propertyName,
        PropertyType $propertyType,
        ModelMethods $modelMethods,
        ?Closure $internalItemsType = null,
    ): void {
        if ($propertyType->fullNamespace !== null) {
            $namespace->removeUse($propertyType->fullNamespace);
            $namespace->addUse($propertyType->fullNamespace);
        }

        $phpType = ($propertyType->scalarType === 'array')
            ? 'array'
            : $propertyType->fullNamespace ?? $propertyType->scalarType;

        $constructorParam = $modelMethods->constructor
            ->addPromotedParameter($propertyName)
            ->setType($phpType)
            ->setNullable($propertyType->isNullable || !$propertyType->isRequired)
            ->setPrivate();

        $this->attributeBuilder->addAttributesFromExtensions($property, $namespace, $constructorParam);

        if ($propertyType->genericString !== null) {
            $modelMethods->constructor->addComment("@param $propertyType->genericString \$$propertyName");
        }

        $modelMethods->getter
            ->setReturnType($phpType)
            ->setReturnNullable($propertyType->isNullable || !$propertyType->isRequired);

        if ($modelMethods->setter !== null) {
            $modelMethods->setter
                ->setReturnType('self')
                ->addBody("\$this->$propertyName = \$$propertyName;")
                ->addBody('return $this;')
                ->addParameter($propertyName)
                ->setType($phpType)
                ->setNullable($propertyType->isNullable || !$propertyType->isRequired);
            if ($propertyType->genericString !== null) {
                $modelMethods->setter
                    ->addComment("@param $propertyType->genericString \$$propertyName");
            }
        } else { // input model
            if (!$propertyType->isRequired) {
                $namespace->addUse(PlatformClasses::UNDEFINED);
                $modelMethods->getter->addBody("if (!in_array('$propertyName', \$this->getListParams())) {");
                if ($propertyType->hasDefault) {
                    $modelMethods->getter->addBody(
                        sprintf("\treturn %s;\n}", PhpSyntax::scalarToRow($propertyType->default))
                    );
                } else {
                    if ($phpType !== 'mixed') {
                        $modelMethods->getter->setReturnType($phpType . '|' . PlatformClasses::UNDEFINED);
                    }
                    $modelMethods->getter->addBody("\treturn new UndefinedValue();\n}");
                }
            }
            $constructorParam->setReadOnly();
            if ($internalItemsType !== null) {
                $this->attributeBuilder->addParameterConstraints(
                    $constructorParam,
                    $property,
                    $internalItemsType,
                    $propertyType->scalarType
                );
            }
        }

        if ($propertyType->hasDefault) {
            $constructorParam->setNullable(false)
                ->setDefaultValue($propertyType->default);
            $modelMethods->getter
                ->setReturnNullable(false);
        } elseif ($propertyType->isNullable || !$propertyType->isRequired) {
            $constructorParam->setDefaultValue(null);
        }

        $modelMethods->getter->addBody("return \$this->$propertyName;");
        if ($propertyType->genericString !== null) {
            $modelMethods->getter
                ->addComment("@return $propertyType->genericString");
        }
    }

    public function buildPropertyType(
        Schema | Reference $property,
        bool $isNullable,
        bool $isRequired,
        ?string $phpType = null
    ): PropertyType {
        if ($phpType !== null) {
            $propertyType = new PropertyType(
                $isNullable,
                $isRequired,
                'object',
                null,
                $phpType
            );
        } elseif ($property->getExtensions()[Extensions::JSON_PARAMS] ?? false) {
            $propertyType = new PropertyType(
                false,
                $isRequired,
                'mixed',
            );
        } elseif ($property->type === 'object' || $property->items?->type === 'object') {
            $propertyType = new PropertyType(
                $isNullable,
                $isRequired,
                $property->type,
                needSubModelGenerate: true
            );
        } else {
            $propertyType = new PropertyType(
                $isNullable,
                $isRequired,
                TypeMapping::map($property->type)
            );
        }

        if ($property->default !== null) {
            $propertyType->default = ($property->enum && $phpType !== null)
                ? new Literal(
                    PhpSyntax::extractClassName($phpType) . '::' . ([$phpType, 'tryFrom']($property->default))->name
                ) : $property->default;
            $propertyType->hasDefault = true;
        }

        return $propertyType;
    }
}
