<?php
/**
 * Factory class representation.
 * @category Lister
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Lister;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Propel\Runtime\ActiveQuery\ModelCriteria;
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
 * @since 0.9
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
    protected $config;

    /** @var Registry Doctrine Entity Manager if set */
    protected $doctrine;

    /**
     * Factory constructor.
     * @param FormFactory $formFactory
     * @param Router $router
     * @param array $config
     * @param Registry|null $doctrine
     * @param mixed $csrfProvider
     */
    public function __construct(
        FormFactory $formFactory,
        Router $router,
        array $config,
        Registry $doctrine = null,
        $csrfProvider = null
    )
    {
        $this->formFactory = $formFactory;
        $this->router = $router;
        $resolver = new OptionsResolver();
        $this->configure($resolver);
        $this->config = $resolver->resolve($config);
        $this->doctrine = $doctrine;

        if (!$csrfProvider) {
            // global csrf protection is off, force option 'use_csrf' to false
            $this->config['global_csrf'] = false;
        }
    }

    /**
     * Create new List object or retrieve it from session, automatically by id.
     * @param string $queryClassNameOrRepositoryName
     * @param string|null $id
     * @param string $translationDomain
     * @param string|null $persistentManagerName
     * @return Lister
     * @throws ListerException
     * @throws \Exception
     */
    public function createList(
        string $queryClassNameOrRepositoryName,
        $id = null,
        string $translationDomain = 'lister',
        $persistentManagerName = null
    ): Lister
    {
        // determine ORM engine
        $orm = $this->config['orm'];
        if ($orm === 'auto') {
            $orm = is_subclass_of($queryClassNameOrRepositoryName, ModelCriteria::class)
                ? 'propel'
                : 'doctrine';
        }
        $list = null;
        if ($this->request instanceof Request && $this->request->hasSession()) {
            $list = Lister::getFromSession($this->request->getSession(), $id);
        }
        if (!$list instanceof Lister) {
            if ($orm === 'doctrine') {
                if (!$this->doctrine instanceof Registry) {
                    throw new ListerException("Cannot use Doctrine, ORM not detected");
                }
                $list = new Lister($id, $this->doctrine->getRepository($queryClassNameOrRepositoryName, $persistentManagerName));
            } else {
                $list = new Lister($id, $queryClassNameOrRepositoryName);
            }

            // default settings
            $list->setPerPage($this->config['perpage']);
            if (!empty($translationDomain)) {
                $list->setTranslationDomain($translationDomain);
            }
        } elseif($orm === 'doctrine') {
            $list->setRepository($this->doctrine->getRepository($queryClassNameOrRepositoryName, $persistentManagerName));
            $list->setQuery($list->getRepository()->createQueryBuilder('e'));
        }

        $formOptions = ['translation_domain' => $list->getTranslationDomain()];
        if ($this->config['global_csrf']) {
            $formOptions['csrf_protection'] = $this->config['use_csrf'];
        }
        $formNamePrefix = empty($this->config['form_name_prefix'])
            ? $list->getId()
            : ($this->config['form_name_prefix'] . '_' . $list->getId());
        /** @var FormBuilder $builder */
        $builder = $this->formFactory->createNamedBuilder(
            $formNamePrefix,
            FormType::class,
            null,
            $formOptions
        );
        $list->setFilterFormBuilder($builder);
        /** @var FormBuilder $builder */
        $builder = $this->formFactory->createNamedBuilder(
            $formNamePrefix . '_sorter',
            FormType::class,
            null,
            $formOptions
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
                    'orm' => 'auto',
                    'perpage' => 50,
                    'form_name_prefix' => 'lister_filters',
                    'use_csrf' => false,
                    'global_csrf' => true,
                ]
            )
            ->setAllowedTypes('orm', 'string')
            ->setAllowedValues('orm', ['doctrine', 'propel', 'auto'])
            ->setAllowedTypes('perpage', 'int')
            ->setAllowedTypes('form_name_prefix', 'string')
            ->setAllowedTypes('use_csrf', 'bool')
            ->setAllowedTypes('global_csrf', 'bool')
            ->setRequired(['orm', 'perpage', 'form_name_prefix', 'use_csrf', 'global_csrf']);
    }
}