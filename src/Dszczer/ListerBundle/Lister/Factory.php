<?php
/**
 * Factory class representation.
 * @category     Lister
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Lister;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Factory
 * @package Dszczer\ListerBundle
 */
class Factory
{
    /** @var Request|null Stored Request. */
    protected $request;
    /** @var FormFactory Stored form factory */
    protected $formFactory;
    /** @var Router Stored router */
    protected $router;
    /** @var array Lister bundle configuration */
    protected $config = [];

    /**
     * Factory constructor.
     * @param FormFactory $formFactory
     * @param Router $router
     * @param array $config
     */
    public function __construct(FormFactory $formFactory, Router $router, array $config)
    {
        $this->formFactory = $formFactory;
        $this->router = $router;
        $resolver = new OptionsResolver();
        $this->configure($resolver);
        $this->config = $resolver->resolve($config);
    }

    /**
     * Create new List object or retrieve it from session, automatically by id.
     * @param string $modelClass
     * @param string $id
     * @param string $translationDomain
     * @return Lister
     */
    public function createList($modelClass, $id = '', $translationDomain = 'lister')
    {
        $list = null;
        if ($this->request instanceof Request && $this->request->hasSession()) {
            $list = Lister::getFromSession($this->request->getSession(), $id);
        }
        if (!$list instanceof Lister) {
            $list = new Lister($id, $modelClass);
            // default settings
            $list->setPerPage($this->config['perpage']);
            if (!empty($translationDomain)) {
                $list->setTranslationDomain($translationDomain);
            }
        }

        $formNamePrefix = empty($this->config['form_name_prefix'])
            ? $list->getId()
            : ($this->config['form_name_prefix'].'_'.$list->getId());
        /** @var FormBuilder $builder */
        $builder = $this->formFactory->createNamedBuilder(
            $formNamePrefix,
            FormType::class,
            null,
            [
                'translation_domain' => $list->getTranslationDomain(),
                'csrf_protection' => $this->config['use_csrf'],
            ]
        );
        $list->setFilterFormBuilder($builder);
        /** @var FormBuilder $builder */
        $builder = $this->formFactory->createNamedBuilder(
            $formNamePrefix.'_sorter',
            FormType::class,
            null,
            [
                'translation_domain' => $list->getTranslationDomain(),
                'csrf_protection' => $this->config['use_csrf'],
            ]
        );
        $list->setSorterFormBuilder($builder);
        $list->setRouter($this->router);

        return $list;
    }

    /**
     * Get Request object from event.
     * @param KernelEvent $event
     */
    public function onKernelRequest(KernelEvent $event)
    {
        $this->request = $event->getRequest();
    }

    /**
     * Configure options.
     * @param OptionsResolver $resolver
     */
    protected function configure(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults(
                [
                    'perpage' => 15,
                    'form_name_prefix' => 'lister_filters',
                    'use_csrf' => false,
                ]
            )
            ->setAllowedTypes('perpage', 'int')
            ->setAllowedTypes('form_name_prefix', 'string')
            ->setAllowedTypes('use_csrf', 'bool')
            ->setRequired(['perpage', 'form_name_prefix', 'use_csrf']);
    }
}