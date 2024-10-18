<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

use Nette\PhpGenerator\ClassLike;

readonly class RequestModels
{
    public const LIST_PARAMS = ['filter', 'sort', 'limit', 'page'];

    public function __construct(
        public ClassLike $request,
        public ClassLikeCollection $models,
        public array $dataProperties,
        public ?ClassLike $data = null,
        public ?ClassLike $query = null
    ) {
        $this->models->addIfAbsent($this->request);
        if (null !== $this->data) {
            $this->models->addIfAbsent($this->data);
        }
        if (null !== $this->query) {
            $this->models->addIfAbsent($this->query);
        }
    }
}
