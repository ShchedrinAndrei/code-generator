<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

use Nette\PhpGenerator\Method;

class ModelMethods
{
    public function __construct(
        public Method $constructor,
        public Method $getter,
        public ?Method $setter,
    ) {
    }
}
