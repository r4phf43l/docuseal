<?php

use Glpi\Marketplace\Api\Plugins;

class PluginDocusealHttpclient extends Plugins
{
    public function __call($method, $args)
    {
        return call_user_func_array([$this->httpClient, $method], $args);
    }
}
