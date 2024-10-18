<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Schema;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Builder\AttributeBuilder;
use Shchandrei\CodeGenerator\Dictionary\Extensions;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Dictionary\PlatformClasses;
use Shchandrei\CodeGenerator\Dictionary\TypeMapping;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;
use Shchandrei\CodeGenerator\Model\ModelMethods;
use Shchandrei\CodeGenerator\Service\CaseConverter;
use Shchandrei\CodeGenerator\Service\SchemaManipulations;

readonly class ModelGenerator
{
    public function __construct(
        private CaseConverter $caseConverter,
        private AttributeBuilder $attributeBuilder,
        private PropertyGenerator $propertyGenerator,
        private string $applicationNs,
        private string $modelSubDomain,
        private string $modelClassSuffix,
        private string $factorySubDomain,
        private string $factoryClassSuffix,
    ) {
    }

    public function generate(
        string $feature,
        Schema $schema,
        ClassLikeCollection $models,
        bool $isInputModel = false
    ): ClassLike {
        $feature = $schema->getExtensions()[Extensions::FEATURE] ?? $feature;
        $modelNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $feature,
                $this->modelSubDomain
            )
        );

        $modelClass = $modelNamespace
            ->addClass(
                $this->caseConverter->toPascal(
                    SchemaManipulations::getSchemaName($schema)
                ) . $this->caseConverter->toPascal(
                    $this->modelClassSuffix
                )
            );
        GeneratorHelper::addGeneratedAnnotation($modelClass);
        $this->attributeBuilder->addAttributesFromExtensions($schema, $modelNamespace, $modelClass);

        $requiredPropNames = $schema->required ?? [];
        $properties = $schema->properties;

        uksort(
            $properties,
            static fn (string $a, string $b): int => in_array($b, $requiredPropNames) <=> in_array($a, $requiredPropNames)
        );

        $modelConstructor = $modelClass->addMethod('__construct');
        $internalItemsType = function (string $internalType) use ($feature): string {
            return PhpSyntax::combineNsParts(
                $this->applicationNs,
                $feature,
                $this->modelSubDomain,
                $internalType . $this->caseConverter->toPascal(
                    $this->modelClassSuffix
                )
            );
        };
        foreach ($properties as $name => $property) {
            $subModel = null;
            $paramName = $this->caseConverter->toCamel($name);
            $propertyType = $this->propertyGenerator->buildPropertyType(
                $property,
                $property->nullable,
                in_array($name, $requiredPropNames),
                $property->getExtensions()[Extensions::PHP_TYPE] ?? null
            );

            if ($propertyType->needSubModelGenerate) {
                $subModel = $this->generate(
                    $feature,
                    $property->items ?? $property,
                    $models,
                    $isInputModel
                );
                $propertyType->fullNamespace = PhpSyntax::getClassLikeFullName($subModel);
            }

            if ($propertyType->scalarType === 'array') {
                $propertyType->genericString = sprintf(
                    'array<int,%s>',
                    $subModel?->getName()
                        ?: TypeMapping::map($property->items?->type)
                        ?: 'mixed'
                );
            }

            $modelMethods = new ModelMethods(
                $modelConstructor,
                $modelClass->addMethod('get' . $this->caseConverter->toPascal($paramName)),
                !$isInputModel
                    ? $modelClass->addMethod('set' . $this->caseConverter->toPascal($paramName))
                    : null
            );

            $this->propertyGenerator->generateProperty(
                $modelNamespace,
                $property,
                $paramName,
                $propertyType,
                $modelMethods,
                $internalItemsType,
            );
        }

        if ($isInputModel) {
            $this->propertyGenerator->generateListParams($modelNamespace, $modelClass);
        } else {
            $models->addIfAbsent($this->generateFactory($modelClass, $feature));
        }

        $models->addIfAbsent($modelClass);

        return $modelClass;
    }

    private function generateFactory(ClassType $modelClass, string $feature): ClassType
    {
        $modelClassFullName = PhpSyntax::getClassLikeFullName($modelClass);
        $modelClassName = $modelClass->getName();

        $factoryNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $feature,
                $this->factorySubDomain
            )
        );
        $factoryNamespace->addUse($modelClassFullName)
            ->addUse(PlatformClasses::ENTITY_LISTING_SERVICE)
            ->addUse(PlatformClasses::LISTING_PARAMS);

        $factoryClass = $factoryNamespace->addClass($modelClassName . $this->factoryClassSuffix)
            ->setReadOnly();
        GeneratorHelper::addTemplateAnnotation($factoryClass);

        $constructor = $factoryClass->addMethod('__construct');
        $constructor->addPromotedParameter('listingService')
            ->setPrivate()
            ->setType(PlatformClasses::ENTITY_LISTING_SERVICE);

        $buildMethod = $factoryClass->addMethod('build');
        $buildMethod->setReturnType($modelClassFullName)
            ->addBody('//@TODO implement method')
            ->addBody("return new $modelClassName();");

        $listMethod = $factoryClass->addMethod('list');
        $listMethod->setReturnType('array')
            ->addBody('//@TODO implement method')
            ->addParameter('listingParams')->setType(PlatformClasses::LISTING_PARAMS);

        return $factoryClass;
    }
}
