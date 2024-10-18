<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Model;

enum StructureType: string
{
    case TYPE_SCALAR = 'scalar';
    case TYPE_ARRAY = 'array';
    case TYPE_OBJECT = 'object';
    case TYPE_ARR_OF_OBJECT = 'arrayOfObject';
}
