<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GetClass extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getClass', [$this, 'getClassName']),
        ];
    }

    public function getClassName(object $object): string
    {
        return (new \ReflectionClass($object))->getShortName();
    }
}
