<?php

namespace App\Http\Controllers;

use Illuminate\Container\BoundMethod;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    public function callAction($method, $parameters)
    {
        return BoundMethod::call(app(), [$this, $method], $parameters);
    }
}
