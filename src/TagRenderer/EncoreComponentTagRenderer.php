<?php

namespace TwigComponentTools\TCTBundle\TagRenderer;

use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use TwigComponentTools\TCTBundle\Naming\ComponentNamingInterface;

class EncoreComponentTagRenderer implements ComponentTagRenderInterface
{
    private TagRenderer $tagRenderer;

    private ComponentNamingInterface $componentNaming;

    public function __construct(
        ComponentNamingInterface $componentNaming,
        TagRenderer $tagRenderer
    ) {
        $this->componentNaming = $componentNaming;
        $this->tagRenderer     = $tagRenderer;
    }

    public function renderTags(
        array $loadedComponents,
        array $extraAttributes = [],
        string $type = '',
        string $entrypointName = null,
        string $packageName = null
    ): string {
        $tags = [];
        $type = empty($type) ? '' : ".$type";

        foreach ($loadedComponents as $name) {
            $entryName = $this->componentNaming->getEntryName($name, $entrypointName, $extraAttributes, $type);

            $jsTags = $this->tagRenderer->renderWebpackScriptTags(
                $entryName,
                $packageName,
                $entrypointName,
                $extraAttributes
            );

            $jsTags = preg_replace(
                "/$entryName(\..*)?\.js\"/",
                "$entryName$1.js\" data-component=\"$name\"",
                $jsTags
            );

            $tags[] = $jsTags;

            $tags[] = $this->tagRenderer->renderWebpackLinkTags(
                $entryName,
                $packageName,
                $entrypointName,
                $extraAttributes
            );
        }

        $tags = array_filter($tags);

        return implode(PHP_EOL, $tags);
    }

    public function renderHeadTags(array $loadedComponents): string
    {
        return $this->renderTags($loadedComponents, ['async' => true, 'defer' => false], 'head');
    }

    public function renderBodyTags(array $loadedComponents): string
    {
        return $this->renderTags($loadedComponents, ['async' => true, 'defer' => true]);
    }
}