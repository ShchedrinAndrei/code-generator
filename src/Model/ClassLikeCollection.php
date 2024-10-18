<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassLike;
use RuntimeException;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Traversable;

class ClassLikeCollection implements Countable, IteratorAggregate
{
    /**
     * @var ClassLike[]
     */
    private array $collection;

    public function __construct(ClassLike ...$classLikes)
    {
        $this->collection = array_combine(
            array_map(static fn(ClassLike $classLike) => PhpSyntax::getClassLikeFullName($classLike), $classLikes),
            $classLikes
        );
    }

    public function mergeIn(self $another): self
    {
        foreach ($another->collection as $key => $item) {
            if (!array_key_exists($key, $this->collection)) {
                $this->collection[$key] = $item;
            }
        }

        return $this;
    }

    public function add(ClassLike ...$classLikes): self
    {
        foreach ($classLikes as $classLike) {
            $name = PhpSyntax::getClassLikeFullName($classLike);
            if (array_key_exists($name, $this->collection)) {
                throw new InvalidArgumentException(sprintf('Collection already contains classLike: %s', $name));
            }
            $this->collection[$name] = $classLike;
        }

        return $this;
    }

    public function addIfAbsent(ClassLike ...$classLikes): self
    {
        foreach ($classLikes as $classLike) {
            $name = PhpSyntax::getClassLikeFullName($classLike);
            if (!array_key_exists($name, $this->collection)) {
                $this->collection[$name] = $classLike;
            }
        }

        return $this;
    }

    public function has(string | ClassLike $item): bool
    {
        $name = $item instanceof ClassLike ? PhpSyntax::getClassLikeFullName($item) : $item;

        return array_key_exists($name, $this->collection);
    }

    public function get(string $name): ClassLike
    {
        if (!$this->has($name)) {
            throw new RuntimeException(sprintf('Namespace %s cannot be found', $name));
        }

        return $this->collection[$name];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->collection);
    }

    public function count(): int
    {
        return count($this->collection);
    }
}
