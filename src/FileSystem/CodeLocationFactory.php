<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\FileSystem;

use LogicException;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\PhpNamespace;
use RuntimeException;
use Shchandrei\CodeGenerator\Dictionary\CodeLocation;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;
use Shchandrei\CodeGenerator\Model\CodeFolder;

readonly class CodeLocationFactory
{
    private array $mapping;

    public function __construct(
        string $dtoNs,
    ) {
        $this->mapping = [
            CodeFolder::Dto->value => $dtoNs,
        ];
    }

    public function buildForPhpNamespace(PhpNamespace $namespace): CodeLocation
    {
        return $this->buildForStringNs($namespace->getName(), true);
    }

    public function buildForClassLike(ClassLike $classLike): CodeLocation
    {
        return $this->buildForStringNs(PhpSyntax::getClassLikeFullName($classLike), false);
    }

    public function buildForStringNs(string $ns, bool $isDir): CodeLocation
    {
        $folder = $this->chooseCodeFolderForStringNs($ns);
        $fileName = $this->convertStringNsToPath($ns, $this->getNsForCodeFolder($folder), $isDir);

        return new CodeLocation($folder, $fileName, $isDir);
    }

    private function chooseCodeFolderForStringNs(string $ns): CodeFolder
    {
        foreach ($this->mapping as $folder => $folderNs) {
            if (str_starts_with($ns, $folderNs)) {
                return CodeFolder::from($folder);
            }
        }

        throw new LogicException(sprintf('Invalid namespace: %s', $ns));
    }

    private function getNsForCodeFolder(CodeFolder $folder): string
    {
        $key = $folder->value;
        if (!array_key_exists($key, $this->mapping)) {
            throw new RuntimeException(sprintf('Unknown CodeFolder: %s', $key));
        }

        return $this->mapping[$key];
    }

    private function convertStringNsToPath(string $ns, string $folderNs, bool $isDir = true): string
    {
        return str_replace(
                PhpSyntax::NS_SEPARATOR,
                DIRECTORY_SEPARATOR,
                substr($ns, strlen($folderNs))
            )
            . ($isDir ? '' : '.php');
    }
}
