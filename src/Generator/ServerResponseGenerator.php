<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use Exception;
use Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Dictionary\Extensions;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Dictionary\PlatformClasses;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;
use Shchandrei\CodeGenerator\Model\ModelMethods;
use Shchandrei\CodeGenerator\Service\CaseConverter;
use Shchandrei\CodeGenerator\Service\SchemaManipulations;

readonly class ServerResponseGenerator implements GeneratorInterface
{
    public function __construct(
        private CaseConverter $caseConverter,
        private PropertyGenerator $propertyGenerator,
        private ModelGenerator $modelGenerator,
        private string $applicationNs,
        private string $responseSubDomain,
        private string $responseClassSuffix,
    ) {
    }

    public function generate(
        string $url,
        string $method,
        array $pathParams,
        Operation $operation,
    ): ClassLikeCollection {
        $operationId = $this->caseConverter->toPascal($operation->operationId);
        $feature = $this->caseConverter->toPascal($operation->tags[array_key_first($operation->tags)]);

        $response = $operation->responses->getResponse('200') ??
            $operation->responses->getResponse('201') ??
            $operation->responses->getResponse('204') ??
            throw new Exception("Operation - $operationId doesn't have any responses!");

        if (empty($response->content['application/json'])) {
            return $this->generateEmptyResponse($response, $feature);
        }

        $models = new ClassLikeCollection();
        $forceFeature = $response->getExtensions()[Extensions::FEATURE]
            ?? $response->content['application/json']->schema->getExtensions()[Extensions::FEATURE]
            ?? $feature;

        $responseNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $forceFeature,
                $this->responseSubDomain
            )
        );
        $responseNamespace->addUse(PlatformClasses::ABSTRACT_RESPONSE);

        $responseClass = $responseNamespace
            ->addClass($operationId . $this->caseConverter->toPascal($this->responseClassSuffix))
            ->setExtends(PlatformClasses::ABSTRACT_RESPONSE);
        GeneratorHelper::addGeneratedAnnotation($responseClass);

        $schema = SchemaManipulations::flattenSchema($response->content['application/json']->schema);
        if (null !== $responseData = $schema->properties['data'] ?? null) {
            $this->generateData($responseClass, $responseData, $feature, $responseNamespace, $models);
        }

        $wrapData = $response->getExtensions()[Extensions::WRAP_DATA] ?? true;
        $responseClass->addMethod('isWrapData')
            ->setPublic()
            ->setReturnType('bool')
            ->addBody(sprintf('return %s;', PhpSyntax::scalarToRow($wrapData)));

        return $models->addIfAbsent($responseClass);
    }

    private function generateEmptyResponse(
        Response $response,
        string $feature
    ): ClassLikeCollection {
        $forceFeature = $response->getExtensions()[Extensions::FEATURE] ?? $feature;
        $responseNamespace = new PhpNamespace(
            PhpSyntax::combineNsParts(
                $this->applicationNs,
                $forceFeature,
                $this->responseSubDomain
            )
        );
        $responseNamespace->addUse(PlatformClasses::ABSTRACT_RESPONSE);
        $responseClass = $responseNamespace
            ->addClass('EmptyResponse')
            ->setExtends(PlatformClasses::ABSTRACT_RESPONSE);

        $responseClass->addMethod('isWrapData')
            ->setPublic()
            ->setReturnType('bool')
            ->addBody('return false;');

        return (new ClassLikeCollection())->addIfAbsent($responseClass);
    }

    private function generateData(
        ClassType $responseClass,
        Schema $responseData,
        string $feature,
        PhpNamespace $responseNamespace,
        ClassLikeCollection $models
    ): void {
        $responseConstructor = $responseClass->addMethod('__construct');
        $responseDataSchema = $responseData->items ?? $responseData;
        $propertyType = $this->propertyGenerator->buildPropertyType(
            $responseData,
            false,
            true
        );
        $responseDataProperty = $this->modelGenerator->generate(
            $feature,
            $responseDataSchema,
            $models
        );
        $models->addIfAbsent($responseDataProperty);
        $propertyType->fullNamespace = PhpSyntax::getClassLikeFullName($responseDataProperty);
        if ($propertyType->scalarType === 'array') {
            $propertyType->genericString = "array<int,{$responseDataProperty->getName()}>";
        }

        $modelMethods = new ModelMethods(
            $responseConstructor,
            $responseClass->addMethod('get' . $this->caseConverter->toPascal('data')),
            $responseClass->addMethod('set' . $this->caseConverter->toPascal('data'))
        );

        $this->propertyGenerator->generateProperty(
            $responseNamespace,
            $responseData,
            'data',
            $propertyType,
            $modelMethods,
        );
    }

    public function getCleanUpNs(): Generator
    {
        yield from [
            $this->responseSubDomain => $this->applicationNs,
        ];
    }
}
