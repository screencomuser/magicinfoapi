<?php

namespace Screencom\MagicinfoApi\Facades;

use Illuminate\Support\Facades\Facade;

class MagicinfoApi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'magicinfoapi';
    }
}
