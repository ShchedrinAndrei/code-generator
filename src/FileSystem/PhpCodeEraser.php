<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\FileSystem;

use Generator;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Nette\PhpGenerator\PhpNamespace;
use Shchandrei\CodeGenerator\Dictionary\CodeLocation;
use Shchandrei\CodeGenerator\Dictionary\PhpSyntax;

readonly class PhpCodeEraser
{
    public function __construct(
        private CodeFileSystemProvider $fileSystemProvider,
        private CodeLocationFactory $locationFactory,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function deleteMatchingNamespaces(string $nsStart, string $nsEnd): Generator
    {
        $location = $this->locationFactory->buildForStringNs($nsStart, true);
        $fileSystem = $this->fileSystemProvider->getFsForCodeFolder($location->folder);

        $features = [null];
        foreach ($fileSystem->listContents($location->path) as $item) {
            if ($item->isDir()) {
                $features[] = $item->path();
            }
        }
        foreach ($features as $feature) {
            $namespace = new PhpNamespace(PhpSyntax::combineNsParts($nsStart, $feature, $nsEnd));
            if (null !== ($message = $this->deletePhpNamespace($namespace))) {
                yield $message;
            }
        }
    }

    public function deletePhpNamespace(PhpNamespace $namespace): ?string
    {
        return $this->delete($this->locationFactory->buildForPhpNamespace($namespace), $namespace->getName());
    }

    private function delete(CodeLocation $codeLocation, string $ns): ?string
    {
        $fileSystem = $this->fileSystemProvider->getFsForCodeFolder($codeLocation->folder);

        return $codeLocation->isDir
            ? $this->deleteDirectory($fileSystem, $codeLocation->path, $ns)
            : $this->deleteFile($fileSystem, $codeLocation->path, $ns);
    }

    /**
     * @throws FilesystemException
     */
    private function deleteDirectory(FilesystemOperator $fileSystem, string $location, string $ns): ?string
    {
        if ($fileSystem->directoryExists($location)) {
            $fileSystem->deleteDirectory($location);

            return "$ns deleted";
        }

        return null;
    }

    /**
     * @throws FilesystemException
     */
    private function deleteFile(FilesystemOperator $fileSystem, string $location, string $ns): ?string
    {
        if ($fileSystem->fileExists($location)) {
            $fileSystem->delete($location);

            return "$ns deleted";
        }

        return null;
    }
}
