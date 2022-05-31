<?php

namespace TwigComponentTools\TCTBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\VarDumper;
use TwigComponentTools\TCTBundle\Loader\ComponentLoaderInterface;
use TwigComponentTools\TCTBundle\TagRenderer\ComponentTagRenderInterface;

class ComponentTagInjectorListener implements EventSubscriberInterface
{

    private ComponentTagRenderInterface $componentTagRenderer;

    private ComponentLoaderInterface $componentLoader;

    public function __construct(
        ComponentTagRenderInterface $componentTagRenderer,
        ComponentLoaderInterface $componentLoader
    ) {
        $this->componentTagRenderer = $componentTagRenderer;
        $this->componentLoader      = $componentLoader;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $response         = $event->getResponse();
        $content          = $response->getContent();
        $loadedComponents = $this->componentLoader->getLoadedComponents();

        if (empty($loadedComponents)) {
            return;
        }

        $headTags = $this->componentTagRenderer->renderHeadTags($loadedComponents);
        $content  = $this->injectAtTag($content, 'TCTHeadEntries', $headTags);

        $bodyTags = $this->componentTagRenderer->renderBodyTags($loadedComponents);
        $content  = $this->injectAtTag($content, 'TCTBodyEntries', $bodyTags);

        $response->setContent($content);
    }

    private function injectAtTag(string $markup, string $tag, string $tagsToInject): string
    {
        return str_replace("<$tag/>", $tagsToInject, $markup);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse'
        ];
    }
}