<?php
final class models_FactoryInstance
{
    public static function getInstance($className)
    {
        return new $className;
    }
}

