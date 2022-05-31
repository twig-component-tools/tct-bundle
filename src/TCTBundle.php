<?php

namespace TwigComponentTools\TCTBundle;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use TwigComponentTools\TCTBundle\DependencyInjection\TCTBundleExtension;

class TCTBundle extends Bundle
{

    public function getContainerExtension(): Extension
    {
        return new TCTBundleExtension();
    }

}