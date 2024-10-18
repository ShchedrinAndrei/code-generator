<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Schema;
use Exception;
use Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Dictionary\TypeMapping;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;
use Shchandrei\CodeGenerator\Model\RequestModels;
use Shchandrei\CodeGenerator\Service\CaseConverter;
use Shchandrei\CodeGenerator\Service\SchemaManipulations;

readonly class ServerRequestGenerator implements GeneratorInterface
{
    public function __construct(
        private CaseConverter $caseConverter,
        private ModelGenerator $modelGenerator,
        private string $applicationNs,
        private string $requestSubDomain,
        private string $requestClassSuffix,
        private string $querySubDomain,
        private string $queryClassSuffix,
    ) {
    }

    /**
     * @param Parameter[] $pathParams
     */
    public function generate(
        string $url,
        string $method,
        array $pathParams,
        Operation $operation,
    ): ClassLikeCollection {
        $operationId = $this->caseConverter->toPascal($operation->operationId);
        $feature = $this->caseConverter->toPascal($operation->tags[array_key_first($operation->tags)]);
        $models = new ClassLikeCollection();

        $requestNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $feature,
                $this->requestSubDomain
            )
        );
        $requestNamespace->addUse(PlatformClasses::ABSTRACT_REQUEST);
        $requestClass = $requestNamespace
            ->addClass($operationId . $this->caseConverter->toPascal($this->requestClassSuffix))
            ->setReadOnly()
            ->setExtends(PlatformClasses::ABSTRACT_REQUEST);
        GeneratorHelper::addGeneratedAnnotation($requestClass);

        $isListParams = $operation->getExtensions()[Extensions::LIST_PARAMS] ?? false;
        $requestConstructor = $this->generateRequestConstructor(
            $requestClass,
            $requestNamespace,
            $isListParams,
            ...array_merge($pathParams)
        );

        if (null !== $operation->requestBody) {
            $this->generateBody(
                $feature,
                $operation->requestBody->content['application/json']->schema,
                $requestNamespace,
                $requestConstructor,
                $requestClass,
                $models
            );
        }

        if ($this->needQueryGenerate($operation, $isListParams)) {
            $this->generateQuery(
                $feature,
                $operationId,
                $operation,
                $requestNamespace,
                $requestConstructor,
                $requestClass,
                $models
            );
        }

        return $models->addIfAbsent($requestClass);
    }

    private function generateRequestConstructor(
        ClassType $class,
        PhpNamespace $phpNamespace,
        bool $isListParams,
        Parameter ...$parameters
    ): Method {
        $constructor = $class->addMethod('__construct');
        foreach ($parameters as $parameter) {
            $paramName = $this->caseConverter->toCamel($parameter->name);
            $phpType = $parameter->getExtensions()[Extensions::PHP_TYPE]
                ?? $parameter->schema->getExtensions()[Extensions::PHP_TYPE]
                ?? null;
            if ($phpType) {
                $type = $phpType;
                $phpNamespace->addUse($phpType);
            } else {
                $type = TypeMapping::map($parameter->schema->type) ?? $parameter->schema->type;
            }
            $isNullable = !$parameter->required;
            $constructor
                ->addPromotedParameter($paramName)
                ->setType($type)
                ->setNullable($isNullable)
                ->setPrivate();

            $class->addMethod('get' . $this->caseConverter->toPascal($paramName))
                ->setReturnType($type)
                ->setReturnNullable($isNullable)
                ->addBody("return \$this->$paramName;");
        }

        if ($isListParams) {
            $constructor
                ->addPromotedParameter('params')
                ->setType(PlatformClasses::LISTING_PARAMS);
            $phpNamespace->addUse(PlatformClasses::LISTING_PARAMS);

            $class->addMethod('getParams')
                ->setReturnType(PlatformClasses::LISTING_PARAMS)
                ->addBody('return $this->params;');
        }

        return $constructor;
    }

    private function generateBody(
        string $feature,
        Schema $schema,
        PhpNamespace $requestNamespace,
        Method $requestConstructor,
        ClassType $requestClass,
        ClassLikeCollection $models
    ): void {
        $schema = SchemaManipulations::flattenSchema($schema);
        $dataClass = $this->modelGenerator->generate($feature, $schema, $models, true);
        $dataClassFullName = PhpSyntax::getClassLikeFullName($dataClass);
        $requestNamespace->addUse($dataClassFullName);

        $requestConstructor->addPromotedParameter('data')
            ->setType($dataClassFullName)
            ->setPrivate();

        $requestClass->addMethod('getData')
            ->setReturnType($dataClassFullName)
            ->addBody('return $this->data;');

        $models->addIfAbsent($dataClass);
    }

    private function generateQuery(
        string $feature,
        string $operationId,
        Operation $operation,
        PhpNamespace $requestNamespace,
        Method $requestConstructor,
        ClassType $requestClass,
        ClassLikeCollection $models
    ): void {
        $queryNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $feature,
                $this->querySubDomain
            )
        );
        $queryClass = $queryNamespace
            ->addClass($operationId . $this->caseConverter->toPascal($this->queryClassSuffix))
            ->setReadOnly();
        GeneratorHelper::addGeneratedAnnotation($queryClass);
        $queryConstructor = $queryClass->addMethod('__construct');
        foreach ($operation->parameters as $parameter) {
            if (in_array($parameter->name, RequestModels::LIST_PARAMS)) {
                continue;
            }

            $queryConstructor->addPromotedParameter($parameter->name)
                ->setDefaultValue(null)
                ->setNullable()
                ->setType(
                    TypeMapping::map($parameter->schema->type) ??
                    throw new Exception(
                        sprintf('Incorrect query type - {%s} for {%s}', $parameter->schema->type, $parameter->name)
                    )
                );
        }
        $queryClassFullName = PhpSyntax::getClassLikeFullName($queryClass);
        $requestNamespace->addUse($queryClassFullName);

        $requestConstructor->addPromotedParameter('query')
            ->setNullable()
            ->setDefaultValue(null)
            ->setType($queryClassFullName)
            ->setPrivate();

        $requestClass->addMethod('getQuery')
            ->setReturnNullable()
            ->setReturnType($queryClassFullName)
            ->addBody('return $this->query;');

        $models->addIfAbsent($queryClass);
    }

    public function getCleanUpNs(): Generator
    {
        yield from [
            $this->requestSubDomain => $this->applicationNs,
            $this->querySubDomain   => $this->applicationNs,
        ];
    }

    private function needQueryGenerate(Operation $operation, bool $isListParams): bool
    {
        $parametersName = array_map(static fn(Parameter $param): string => $param->name, $operation->parameters);

        if ($isListParams) {
            if (array_diff($parametersName, RequestModels::LIST_PARAMS)) {
                return true;
            }
        } else {
            return !empty($parametersName);
        }

        return false;
    }
}
