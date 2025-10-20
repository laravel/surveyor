<?php

namespace Laravel\Surveyor\Analysis;

enum EntityType: string
{
    case CLASS_TYPE = 'class';
    case METHOD_TYPE = 'method';
    case INTERFACE_TYPE = 'interface';
    case TRAIT_TYPE = 'trait';
    case ENUM_TYPE = 'enum';
    case FUNCTION_TYPE = 'function';
    case CONSTANT_TYPE = 'constant';
}
