<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Dictionary;

use Shchandrei\CodeGenerator\Model\CodeFolder;

final readonly class CodeLocation
{
    public function __construct(
        public CodeFolder $folder,
        public string $path,
        public bool $isDir = false,
    ) {
    }
}
