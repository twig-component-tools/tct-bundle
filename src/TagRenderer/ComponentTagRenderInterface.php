<?php

namespace TwigComponentTools\TCTBundle\TagRenderer;

interface ComponentTagRenderInterface
{
    public function renderHeadTags(array $loadedComponents): string;

    public function renderBodyTags(array $loadedComponents): string;
}