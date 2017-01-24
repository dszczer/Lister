<?php
/**
 * Lister twig extension class representation.
 * @category     Twig Extension
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Twig;

use Dszczer\ListerBundle\Lister\Lister;
use Symfony\Component\Form\Form;

/**
 * Class ListerExtension
 * @package Dszczer\ListerBundle
 */
class ListerExtension extends \Twig_Extension
{
    /**
     * Get all extension functions.
     * @see \Twig_Extension
     */
    public function getFunctions(): array
    {
        return [
            new \Twig_SimpleFunction(
                'lister_filters', [$this, 'listerFiltersFunction'], [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
            new \Twig_SimpleFunction(
                'lister_body', [$this, 'listerBodyFunction'], [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
            new \Twig_SimpleFunction(
                'lister_pagination', [$this, 'listerPaginationFunction'], [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
        ];
    }

    /**
     * Render filters.
     * @param \Twig_Environment $env
     * @param Lister $list
     * @return string
     */
    public function listerFiltersFunction(\Twig_Environment $env, Lister $list): string
    {
        $form = $list->getFilterForm();

        return $env->render(
            $list->getFilterLayout(),
            ['list' => $list, 'formView' => $form instanceof Form ? $form->createView() : null]
        );
    }

    /**
     * Render body.
     * @param \Twig_Environment $env
     * @param Lister $list
     * @return string
     */
    public function listerBodyFunction(\Twig_Environment $env, Lister $list): string
    {
        return $env->render($list->getListLayout(), ['list' => $list, 'results' => $list->getPager()->getResults()]);
    }

    /**
     * Render pagination.
     * @param \Twig_Environment $env
     * @param Lister $list
     * @return string
     */
    public function listerPaginationFunction(\Twig_Environment $env, Lister $list): string
    {
        return $env->render($list->getPaginationLayout(), [
            'list' => $list,
            'pagination' => $list->getPager(),
        ]);
    }

    /**
     * Returns the name of the extension.
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'lister_twig_extension';
    }
}