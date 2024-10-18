<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\FileSystem;

use Generator;
use League\Flysystem\FilesystemException;
use LogicException;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Shchandrei\CodeGenerator\Model\BundleGeneration\ComposerJsonFile;
use Shchandrei\CodeGenerator\Model\BundleGeneration\Config;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;
use Symfony\Component\Yaml\Yaml;

readonly class PhpCodeDumper
{
    public function __construct(
        private CodeFileSystemProvider $fileSystemProvider,
        private CodeLocationFactory $locationFactory,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function dumpClassLikes(ClassLikeCollection $namespaces): Generator
    {
        foreach ($namespaces as $namespace) {
            if (null !== ($message = $this->dumpClassLike($namespace))) {
                yield $message;
            }
        }
    }

    /**
     * @throws FilesystemException
     */
    public function dumpClassLike(ClassLike $classLike): ?string
    {
        $file = new PhpFile();
        /** @noinspection PhpDeprecationInspection */
        $file->setStrictTypes()->addNamespace($classLike->getNamespace());

        $codeLocation = $this->locationFactory->buildForClassLike($classLike);
        $fileSystem = $this->fileSystemProvider->getFsForCodeFolder($codeLocation->folder);

        if ($codeLocation->isDir) {
            throw new LogicException('Cannot dump code of a directory');
        }

        if (!$fileSystem->fileExists($codeLocation->path)) {
            $file->addComment($classLike->getComment() ?? '');
            $classLike->setComment(null);
            $fileSystem->write($codeLocation->path, (new PsrPrinter())->printFile($file));
        } else {
            return "File: '$codeLocation->path' already exists, skipping.";
        }

        return null;
    }
}
