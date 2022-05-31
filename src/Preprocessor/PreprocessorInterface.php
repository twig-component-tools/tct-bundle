<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use Twig\Source;

interface PreprocessorInterface
{
    public function processSourceContext(Source $source): Source;
}