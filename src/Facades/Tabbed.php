<?php

namespace JibayMcs\Tabbed\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JibayMcs\Tabbed\Tabbed
 */
class Tabbed extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \JibayMcs\Tabbed\Tabbed::class;
    }
}
