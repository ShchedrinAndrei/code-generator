<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Generator;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use Generator;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;

interface GeneratorInterface
{
    /**
     * @param $pathParams Parameter[]
     */
    public function generate(
        string $url,
        string $method,
        array $pathParams,
        Operation $operation,
    ): ClassLikeCollection;

    public function getCleanUpNs(): Generator;
}