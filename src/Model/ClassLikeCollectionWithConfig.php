<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

class ClassLikeCollectionWithConfig
{
    private array $configs;

    public function __construct(
        private readonly ClassLikeCollection $collection,
        TransportConfig ...$configs
    ) {
        $this->configs = $configs;
    }

    public function mergeIn(self $another): self
    {
        $this->collection->mergeIn($another->collection);
        $this->configs = array_merge($another->configs, $this->configs);

        return $this;
    }

    public function getClassLikes(): ClassLikeCollection
    {
        return $this->collection;
    }

    public function getConfigs(): array
    {
        return $this->configs;
    }
}
