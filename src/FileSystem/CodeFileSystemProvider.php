<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\FileSystem;

use League\Flysystem\FilesystemOperator;
use Shchandrei\CodeGenerator\Model\CodeFolder;

final readonly class CodeFileSystemProvider
{
    public function __construct(
        private FilesystemOperator $projectDomainDir,
        private FilesystemOperator $projectApplicationDir,
        private FilesystemOperator $projectInfrastructureDir,
        private FilesystemOperator $projectTestsDir,
        private FilesystemOperator $projectConfigDir,
        private FilesystemOperator $bundleDir,
        private FilesystemOperator $bundleResourcesDir,
        private FilesystemOperator $bundleBaseDir,
    ) {
    }

    public function getFsForCodeFolder(CodeFolder $codeFolder): FilesystemOperator
    {
        return match ($codeFolder) {
            CodeFolder::Domain => $this->projectDomainDir,
            CodeFolder::Application => $this->projectApplicationDir,
            CodeFolder::Infrastructure => $this->projectInfrastructureDir,
            CodeFolder::Tests => $this->projectTestsDir,
            CodeFolder::Bundle => $this->bundleDir,
        };
    }

    public function getConfigFolder(): FilesystemOperator
    {
        return $this->projectConfigDir;
    }

    public function getFsForBundleConfig(): FilesystemOperator
    {
        return $this->bundleResourcesDir;
    }

    public function getFsForBaseDirFiles(): FilesystemOperator
    {
        return $this->bundleBaseDir;
    }
}
