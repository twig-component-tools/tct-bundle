<?php

namespace TwigComponentTools\TCTBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorybookController extends AbstractController
{
    /**
     * @Route (
     *     path="/",
     *     name="tct_render_storybook",
     *     methods={"POST"}
     * )
     */
    #[Route(path: '/', name: 'tct_render_storybook', methods: ['POST'])]
    public function storybookComponent(Request $request): Response
    {
        $template = $request->request->get('template');
        $data     = $request->request->all('data');

        return $this->render($template, $data);
    }
}
