<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

use Nette\PhpGenerator\ClassLike;

readonly class ResponseModels
{
    public function __construct(
        public ClassLike $successResponse,
        public ClassLikeCollection $models,
        public ?string $responsePropertyFullName = null,
        public bool $isArray = false
    ) {
        $this->models->addIfAbsent($this->successResponse);
    }
}
