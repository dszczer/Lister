<?php
/**
 * Lister class representation.
 * @category Lister
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Lister;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Dszczer\ListerBundle\Element\Element;
use Dszczer\ListerBundle\Element\ElementBag;
use Dszczer\ListerBundle\Filter\Filter;
use Dszczer\ListerBundle\Filter\FilterBag;
use Dszczer\ListerBundle\Sorter\Sorter;
use Dszczer\ListerBundle\Sorter\SorterBag;
use Dszczer\ListerBundle\Util\Helper;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Lister
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
class Lister
{
    /** string key used to store all lists in session */
    const SERIALIZED_STORAGE_NAME = 'lister_serialized_objects';

    /** @var  string UUID */
    protected $id = '';

    /** @var  ModelCriteria|QueryBuilder|null Query to use when applying list */
    protected $query;

    /** @var  ModelCriteria|QueryBuilder|null Query from external source */
    protected $externalQuery;

    /** @var  EntityRepository|null Doctrine Entity repository @since 1.0 */
    protected $repository;

    /** @var  FilterBag Bag of filters */
    protected $filters;

    /** @var  SorterBag Bag of sorters */
    protected $sorters;

    /** @var  ElementBag Bag of elements */
    protected $elements;

    /** @var  PagerHelper Pagination helper */
    protected $pager;

    /** @var  int Quantity of rows per page */
    protected $perPage = 0;

    /** @var  int Current page */
    protected $currentPage = 1;

    /** @var  boolean Flag to persist Lister object into Session or not */
    protected $persist = true;

    /** @var  boolean Flag to determine dynamic (JavaScript) or static control of list */
    protected $dynamic = true;

    /** @var  bool Flag to rebuild query object when apply method is called */
    protected $rebuildQuery = false;

    /** @var  string Layout file for filter */
    protected $filterLayout = '@DszczerListerBundle:Lister:filter.html.twig';

    /** @var  string Layout file for list */
    protected $listLayout = '@DszczerListerBundle:Lister:table.html.twig';

    /** @var  string Layout file for single element */
    protected $elementLayout = '@DszczerListerBundle:Lister:tableElement.html.twig';

    /** @var  string Layout file for pagination */
    protected $paginationLayout = '@DszczerListerBundle:Lister:pagination.html.twig';

    /** @var  string Translation domain for views */
    protected $translationDomain = 'default';

    /** @var  FormBuilder|Form|null Filter form builder, when form not build, Form when form is built */
    protected $filterForm;

    /** @var  Form|null Empty filter form */
    protected $emptyFilterForm;

    /** @var  FormBuilder|null Sorter form builder */
    protected $sorterFormBuilder;

    /** @var  Router|null Router to generate paths */
    protected $router;

    /** @var array User defined options */
    protected $customOptions = [];

    /**
     * Lister constructor.
     * @param string|null $id Unique identifier
     * @param string|null|EntityRepository $queryClassNameOrRepository Full class name of Propel ModelCriteria or Doctrine Repository object
     * @throws ListerException
     */
    public function __construct($id = null, $queryClassNameOrRepository = null)
    {
        if (!empty($queryClassNameOrRepository)) {
            if ($queryClassNameOrRepository instanceof EntityRepository) {
                // Doctrine
                $this->repository = $queryClassNameOrRepository;
                $this->query = $this->repository->createQueryBuilder('e');
            } else {
                // Propel
                $query = new $queryClassNameOrRepository();
                if (!$query instanceof ModelCriteria) {
                    throw new \InvalidArgumentException('Class is not an instance of ModelCriteria');
                }
                unset($query);
                $this->query = call_user_func($queryClassNameOrRepository . '::create');
            }
        }
        $this->filters = new FilterBag();
        $this->sorters = new SorterBag();
        $this->elements = new ElementBag();

        if (empty($id)) {
            $id = Helper::uuidv4();
            $this->persist = false;
        }
        $this->id = $id;
        $this->setCustomOptions([]);
    }

    /**
     * Get stored lister object from session storage.
     * @param SessionInterface $session
     * @param string $uuid ID of the list to read from
     * @return Lister|null Lister for found one, null for not
     * @throws \Exception
     */
    public static function getFromSession(SessionInterface $session, string $uuid)
    {
        if (!is_scalar($uuid)) {
            throw new \InvalidArgumentException("Argument 'uuid' is not scalar");
        }
        $uuid = (string)$uuid;

        if ($session->has(static::SERIALIZED_STORAGE_NAME)) {
            $lists = $session->get(static::SERIALIZED_STORAGE_NAME, []);
            if (!empty($lists[$uuid])) {
                $data = $lists[$uuid];
                if (isset($data['id']) && $data['id'] === $uuid) {
                    $object = new self;
                    $object->unserialize($data);
                    if ($object->getId() === $uuid) {
                        return $object;
                    }
                    unset($object);
                }
            }
        }

        return null;
    }

    /**
     * Remove stored lister object from session storage.
     * @param SessionInterface $session
     * @param string $uuid ID of the list to remove
     * @return bool True on success, false on failure
     * @throws \Exception
     */
    public static function removeFromSession(SessionInterface $session, string $uuid): bool
    {
        if ($session->has(static::SERIALIZED_STORAGE_NAME)) {
            $lists = $session->get(static::SERIALIZED_STORAGE_NAME, []);
            if (array_key_exists($uuid, $lists)) {
                $object = new self;
                try {
                    $object->unserialize($lists[$uuid]);
                    if ($object->getId() === $uuid) {
                        unset($object);
                        unset($lists[$uuid]);
                        $session->set(static::SERIALIZED_STORAGE_NAME, $lists);

                        return true;
                    }
                } catch (\InvalidArgumentException $ex) {
                    // broken Lister object, remove
                    unset($lists[$uuid]);
                    $session->set(static::SERIALIZED_STORAGE_NAME, $lists);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Store self into session storage.
     * @param SessionInterface $session
     * @param bool $overwrite Overwrite existing object with same list's ID
     * @return bool True on success, false on failure
     */
    public function storeInSession(SessionInterface $session, bool $overwrite = true): bool
    {
        $lists = $session->get(static::SERIALIZED_STORAGE_NAME, []);
        if (!$this->isPersist() || (isset($lists[$this->id]) && !$overwrite)) {
            return false;
        }
        $lists[$this->id] = $this->serialize();
        $session->set(static::SERIALIZED_STORAGE_NAME, $lists);

        return true;
    }

    /**
     * Check if list should use Javascript or be static
     * @return bool True for Javascript, false for static
     */
    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    /**
     * True to use dynamic controls, false for static.
     * @param bool $dynamic
     * @return Lister
     */
    public function setDynamic(bool $dynamic): Lister
    {
        $this->dynamic = $dynamic;

        return $this;
    }

    /**
     * Get pagination helper or null if list was not applied yet.
     * @return PagerHelper|null
     */
    public function getPager()
    {
        return $this->pager;
    }

    /**
     * Set external query object to use as base for filtering, paginating and sorting.
     * @param ModelCriteria|QueryBuilder $query
     * @return Lister
     */
    public function setQuery($query): Lister
    {
        $this->query = $query;
        $this->externalQuery = clone $query;

        return $this;
    }

    /**
     * Get external query object, if set.
     * @param bool $clone True for cloned or false for original model criteria
     * @return ModelCriteria|QueryBuilder|null
     */
    public function getQuery(bool $clone = true)
    {
        return $clone && $this->query ? clone $this->query : $this->query;
    }

    /**
     * Set external repository object to use as base for filtering, paginating and sorting.
     * @param EntityRepository $repository
     * @return Lister
     * @since 1.0
     */
    public function setRepository(EntityRepository $repository): Lister
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Get related Doctrine repository, if set.
     * @return EntityRepository|null|string
     * @since 1.0
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Set quantity of Elements per one page.
     * @param int $int
     * @return Lister
     */
    public function setPerPage(int $int): Lister
    {
        $this->perPage = max(0, $int);

        return $this;
    }

    /**
     * Get quantity of ELements per one page.
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * True to allow, false to disallow storing in session.
     * @param bool $state
     * @return Lister
     */
    public function setPersist(bool $state): Lister
    {
        $this->persist = $state;

        return $this;
    }

    /**
     * Check if list can be stored in session.
     * @return bool
     */
    public function isPersist(): bool
    {
        return $this->persist;
    }

    /**
     * Get current displayed page.
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get current displayed page HTTP GET parameter's name.
     * @return string
     */
    public function getPageRequestParameterName(): string
    {
        return 'p_' . $this->getId();
    }

    /**
     * Get id of list.
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Helper to create Filter, Sorter and Element at once and attach them to Lister.
     * @param string $name Common name.
     * @param string $label Common label
     * @param bool $sort True to use sorting, false to not
     * @param string $filterType Filter type or empty string to not use
     * @param string $filterMethod Filter method
     * @param mixed $filterValue Filter value or null if not set
     * @param array $filterValues Enum filter values or empty array of not set
     * @param string $sorterMethod Sorter method
     * @param mixed $sorterValue Sorter value or null if not set
     * @param string $elementMethod Element method
     * @param callable|null $elementCallable Element callable or null if not set
     * @param mixed $elementData ELement data or null if not set
     * @return Lister
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     */
    public function addField(
        string $name,
        string $label,
        bool $sort = false,
        string $filterType = '',
        string $filterMethod = '',
        $filterValue = null,
        array $filterValues = [],
        string $sorterMethod = '',
        $sorterValue = null,
        string $elementMethod = '',
        $elementCallable = null,
        $elementData = null
    ): Lister
    {
        $this->addElement(new Element($name, $label, $elementMethod, $elementCallable, $elementData));
        if ($sort) {
            $this->addSorter(new Sorter($name, $label, $sorterMethod, $sorterValue));
        }
        if ($filterType) {
            $this->addFilter(new Filter($filterType, $name, $label, $filterMethod, $filterValue, $filterValues));
        }

        return $this;
    }

    /**
     * Get array of filters.
     * @return Filter[]|array
     */
    public function getFilters(): array
    {
        return $this->filters->all();
    }

    /**
     * Get filter by it's name.
     * @param string $name
     * @return Filter|null
     */
    public function getFilter(string $name)
    {
        return $this->filters->get($name);
    }

    /**
     * Replace filters with new ones.
     * @param FilterBag $filters
     * @param bool $overwrite True for overwrite, false for merge (existing filters wll not be modified)
     * @return Lister
     */
    public function setFilters(FilterBag $filters, bool $overwrite = false): Lister
    {
        if ($overwrite) {
            $this->filters->replace($filters->all());
        } else {
            /** @var Filter $filter */
            foreach ($filters->all() as $filter) {
                $this->addFilter($filter, false);
            }
        }

        return $this;
    }

    /**
     * Add filter to list.
     * @param Filter $filter
     * @param bool $overwrite True for overwrite if exists, false to don't
     * @return Lister
     */
    public function addFilter(Filter $filter, bool $overwrite = false): Lister
    {
        if ($overwrite || !$this->hasFilter($filter)) {
            $this->filters->set($filter->getName(), $filter);
        }

        return $this;
    }

    /**
     * Check if filter is defined in list.
     * @param string|Filter $name
     * @return bool
     */
    public function hasFilter($name): bool
    {
        return $this->filters->has($name instanceof Filter ? $name->getName() : $name);
    }

    /**
     * Remove filter from list.
     * @param string $name
     * @return Lister
     */
    public function removeFilter(string $name): Lister
    {
        $this->filters->remove($name);

        return $this;
    }

    /**
     * Get array of sorters.
     * @return Sorter[]|array
     */
    public function getSorters(): array
    {
        return $this->sorters->all();
    }

    /**
     * Get sorter by it's name.
     * @param string $name
     * @return Sorter|null
     */
    public function getSorter(string $name)
    {
        return $this->sorters->get($name);
    }

    /**
     * Replace sorters with new ones.
     * @param SorterBag $sorters
     * @param bool $overwrite True for overwrite, false for merge (existing sorters will not be modified)
     * @return Lister
     */
    public function setSorters(SorterBag $sorters, bool $overwrite = false): Lister
    {
        if ($overwrite) {
            $this->sorters->replace($sorters->all());
        } else {
            /** @var Sorter $sorter */
            foreach ($sorters->all() as $sorter) {
                $this->addSorter($sorter, false);
            }
        }

        return $this;
    }

    /**
     * Add sorter to list.
     * @param Sorter $sorter
     * @param bool $overwrite True for overwrite if exists, fals to don't
     * @return Lister
     */
    public function addSorter(Sorter $sorter, bool $overwrite = false): Lister
    {
        if ($overwrite || !$this->hasSorter($sorter)) {
            $this->sorters->set($sorter->getName(), $sorter);
        }

        return $this;
    }

    /**
     * Check if sorter is defined in list.
     * @param string|Sorter $name
     * @return bool
     */
    public function hasSorter($name): bool
    {
        return $this->sorters->has($name instanceof Sorter ? $name->getName() : $name);
    }

    /**
     * Remove sorter from list.
     * @param string $name
     * @return Lister
     */
    public function removeSorter(string $name): Lister
    {
        $this->sorters->remove($name);

        return $this;
    }

    /**
     * Get array of elements.
     * @return Element[]|array
     */
    public function getElements(): array
    {
        return $this->elements->all();
    }

    /**
     * Get element by it's name.
     * @param string $name
     * @return Element|null
     */
    public function getElement(string $name)
    {
        return $this->elements->get($name);
    }

    /**
     * Replave elements with new ones.
     * @param ElementBag $elements
     * @param bool $overwrite True for replace, false for merge (existing elements won't be modified)
     * @return Lister
     */
    public function setElements(ElementBag $elements, bool $overwrite = false): Lister
    {
        if ($overwrite) {
            $this->elements->replace($elements->all());
        } else {
            /** @var Element $element */
            foreach ($elements->all() as $element) {
                $this->addElement($element, false);
            }
        }

        return $this;
    }

    /**
     * Add Element to list.
     * @param Element $element
     * @param bool $overwrite True for overwrite if exists, false to don't
     * @return Lister
     */
    public function addElement(Element $element, bool $overwrite = false): Lister
    {
        if ($overwrite || !$this->hasElement($element->getName())) {
            $this->elements->set($element->getName(), $element);
        }

        return $this;
    }

    /**
     * Check if element is defined in list.
     * @param string $name
     * @return bool
     */
    public function hasElement(string $name): bool
    {
        return $this->elements->has($name);
    }

    /**
     * Remove element from list.
     * @param string $name
     * @return Lister
     */
    public function removeElement(string $name): Lister
    {
        $this->elements->remove($name);

        return $this;
    }

    /**
     * Get filter form display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getFilterLayout(bool $raw = false): string
    {
        return $raw ? $this->filterLayout : Helper::fixTwigTemplatePath($this->filterLayout);
    }

    /**
     * Set filter form display layout twig path.
     * @param string $filterLayout Twig path to file.
     * @return Lister
     */
    public function setFilterLayout(string $filterLayout): Lister
    {
        $this->filterLayout = $filterLayout;

        return $this;
    }

    /**
     * Get list display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getListLayout(bool $raw = false): string
    {
        return $raw ? $this->listLayout : Helper::fixTwigTemplatePath($this->listLayout);
    }

    /**
     * Set list display layout twig path.
     * @param string $listLayout
     * @return Lister
     */
    public function setListLayout(string $listLayout): Lister
    {
        $this->listLayout = $listLayout;

        return $this;
    }

    /**
     * Get element display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getElementLayout(bool $raw = false): string
    {
        return $raw ? $this->elementLayout : Helper::fixTwigTemplatePath($this->elementLayout);
    }

    /**
     * Set element display layout twig path.
     * @param string $elementLayout
     * @return Lister
     */
    public function setElementLayout(string $elementLayout): Lister
    {
        $this->elementLayout = $elementLayout;

        return $this;
    }

    /**
     * Get pagination display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getPaginationLayout(bool $raw = false): string
    {
        return $raw ? $this->paginationLayout : Helper::fixTwigTemplatePath($this->paginationLayout);
    }

    /**
     * Set pagination display layout twig path.
     * @param string $paginationLayout
     * @return Lister
     */
    public function setPaginationLayout(string $paginationLayout): Lister
    {
        $this->paginationLayout = $paginationLayout;

        return $this;
    }

    /**
     * Get translation domain.
     * @return string
     */
    public function getTranslationDomain(): string
    {
        return $this->translationDomain;
    }

    /**
     * Set translation domain.
     * @param string $domain
     * @return Lister
     */
    public function setTranslationDomain(string $domain): Lister
    {
        $this->translationDomain = $domain;

        return $this;
    }

    /**
     * Get user defined options.
     * @return array
     */
    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    /**
     * Set user defined options.
     * @param array $customOptions
     * @return Lister
     * @throws ListerException
     */
    public function setCustomOptions(array $customOptions): Lister
    {
        $this->customOptions = $this->resolveCustomOptions($customOptions);

        return $this;
    }

    /**
     * Resolve some mandatory options.
     * @param array $options Options to resolve
     * @return array Resolved options
     * @throws ListerException
     */
    protected function resolveCustomOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults(
                array_replace_recursive(
                    $options,
                    [
                        'maxLinks' => 7,
                        'route' => 'lister_quick_reload',
                        'params' => ['uuid' => $this->id],
                    ]
                )
            )
            ->setRequired(['maxLinks', 'route', 'params'])
            ->setAllowedTypes('maxLinks', 'int')
            ->setAllowedTypes('route', 'string')
            ->setAllowedTypes('params', 'array');

        try {
            return $resolver->resolve($options);
        } catch (\Throwable $throwable) {
            throw new ListerException('Invalid customOptions', 0, $throwable);
        }
    }

    /**
     * Return built filter form or null if has not been build.
     * @return Form|null
     */
    public function getFilterForm()
    {
        return $this->filterForm instanceof Form ? $this->filterForm : null;
    }

    /**
     * @internal Used only by factory.
     * @param FormBuilder $filterForm
     * @return Lister
     */
    public function setFilterFormBuilder(FormBuilder $filterForm): Lister
    {
        $this->filterForm = $filterForm;

        return $this;
    }

    /**
     * Builds filter form to manipulate list.
     * @return bool True on successful build, false on fail
     */
    protected function buildFilterForm(): bool
    {
        $formBuilder = $this->filterForm;
        if ($formBuilder instanceof FormBuilder && $this->filters->count() > 0) {
            $emptyFormBuilder = clone $formBuilder;
            if ($this->isPersist() && $this->isDynamic() && $this->router instanceof Router) {
                $formBuilder->setAction($this->router->generate('lister_quick_reload', ['uuid' => $this->getId()]));
            }
            $formBuilder->add(
                '_lister_id',
                HiddenType::class,
                [
                    'data' => $this->getId(),
                    'empty_data' => $this->getId(),
                ]
            );
            $emptyFormBuilder->add(
                '_lister_id',
                HiddenType::class,
                [
                    'data' => $this->getId(),
                    'empty_data' => $this->getId(),
                ]
            );
            foreach ($this->filters as $filter) {
                $options = [
                    'label' => $filter->getLabel(),
                    'data' => $filter->getValue(),
                    'empty_data' => $filter->getValue(),
                    'mapped' => false,
                    'required' => false,
                    'translation_domain' => $this->getTranslationDomain(),
                ];
                if ($filter->getType() === ChoiceType::class) {
                    $type = $filter->getType(false);
                    $options = array_merge(
                        $options,
                        [
                            'choices' => $filter->getValues(),
                            'multiple' => in_array($type, [Filter::TYPE_MULTISELECT, Filter::TYPE_CHECKBOX], true),
                            'expanded' => in_array($type, [Filter::TYPE_CHECKBOX, Filter::TYPE_RADIO], true),
                        ]
                    );
                }
                $formBuilder->add($filter->getName(), $filter->getType(), $options);
                $options['data'] = null;
                $options['empty_data'] = null;
                $emptyFormBuilder->add($filter->getName(), $filter->getType(), $options);
            }
            $formBuilder
                ->add(
                    'submit',
                    SubmitType::class,
                    [
                        'label' => 'Apply',
                    ]
                )
                ->add(
                    'reset',
                    SubmitType::class,
                    [
                        'label' => 'Clear',
                    ]
                );
            $emptyFormBuilder
                ->add(
                    'submit',
                    SubmitType::class,
                    [
                        'label' => 'Apply',
                    ]
                )
                ->add(
                    'reset',
                    SubmitType::class,
                    [
                        'label' => 'Clear',
                    ]
                );
            $this->filterForm = $formBuilder->getForm();
            $this->emptyFilterForm = $emptyFormBuilder->getForm();

            return true;
        }

        return false;
    }

    /**
     * Return built sorter form or null if form builder is not set.
     * @param Sorter|string $sorter Sorter object or it's string name
     * @return Form|null
     * @throws ListerException
     */
    public function getSorterForm($sorter)
    {
        if ($this->sorterFormBuilder instanceof FormBuilder) {
            $data = $sorter instanceof Sorter ? $sorter : $this->sorters->get($sorter);
            if (!$data instanceof Sorter) {
                throw new ListerException(sprintf('Cannot build sorter form because "%s" does not exist.', $sorter));
            }

            return $this->buildSorterForm($data);
        }

        return null;
    }

    /**
     * @internal Used only by factory.
     * @param FormBuilder $builder
     * @return Lister
     */
    public function setSorterFormBuilder(FormBuilder $builder): Lister
    {
        $this->sorterFormBuilder = $builder;

        return $this;
    }

    /**
     * Build sorter form.
     * @param Sorter $sorter
     * @return Form
     */
    protected function buildSorterForm(Sorter $sorter): Form
    {
        $formBuilder = clone $this->sorterFormBuilder;
        $formBuilder->add(
            '_lister_id',
            HiddenType::class,
            [
                'data' => $this->getId(),
                'empty_data' => $this->getId(),
            ]
        );
        if ($this->isPersist() && $this->isDynamic() && $this->router instanceof Router) {
            $formBuilder->setAction($this->router->generate('lister_quick_reload', ['uuid' => $this->getId()]));
        }
        switch ($sorter->getValue()) {
            default:
                $class = '';
                break;
            case 'ASC':
                $class = 'sort-asc';
                break;
            case 'DESC':
                $class = 'sort-desc';
                break;
        }

        $options = [
            'label' => $sorter->getLabel(),
            'translation_domain' => $this->getTranslationDomain(),
            'attr' => ['class' => $class],
        ];
        $formBuilder->add($sorter->getName(), SubmitType::class, $options);

        return $formBuilder->getForm();
    }

    /**
     * Set router to generate URL paths.
     * @internal Used only by factory.
     * @param Router $router
     * @return Lister
     */
    public function setRouter(Router $router): Lister
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Handle request to resolve filtering, sorting and pagination.
     * @param Request $request
     * @return bool True on handled request, false on failure
     * @throws ListerException
     */
    public function handleRequest(Request $request): bool
    {
        $handled = $clearFilters = false;
        if ($this->filterForm instanceof Form) {
            $this->filterForm->handleRequest($request);
            if ($this->filterForm->isSubmitted() && $this->filterForm->isValid()) {
                $clearFilters = $this->filterForm->get('reset')->isClicked();
                /** @var Form[] $data */
                $data = $this->filterForm->all();
                foreach ($this->getFilters() as $filter) {
                    if (isset($data[$filter->getName()])) {
                        $fieldData = $data[$filter->getName()]->getData();
                        if ($clearFilters) {
                            $fieldData = null;
                        } else {
                            $fieldData = empty($fieldData) ? null : $fieldData;
                        }
                        $filter->setValue($fieldData);
                    }
                }
                if ($clearFilters) {
                    $this->filterForm = $this->emptyFilterForm;
                }

                $handled = true;
            };
        }
        foreach ($this->getSorters() as $sorter) {
            $sorterForm = $this->getSorterForm($sorter);
            if (!$sorterForm instanceof Form) {
                continue;
            }
            $sorterForm->handleRequest($request);
            if ($sorterForm->isSubmitted() && $sorterForm->isValid()) {
                $sorterName = $sorterForm->getClickedButton()->getName();
                if ($this->hasSorter($sorterName)) {
                    $clickedSorter = $this->getSorter($sorterName);
                    $sort = empty($clickedSorter->getValue())
                        ? 'ASC'
                        : ($clickedSorter->getValue() == 'ASC' ? 'DESC' : 'ASC');
                    $clickedSorter->setValue($sort);
                    foreach ($this->getSorters() as $clearSort) {
                        if ($clearSort->getName() === $sorterName) {
                            continue;
                        }
                        $clearSort->setValue(null);
                    }
                    break;
                }
            }
        }

        if ($handled || $clearFilters) {
            $this->currentPage = 1;
        } else {
            $this->currentPage = max(1, intval($request->get($this->getPageRequestParameterName(), 1)));
        }

        return $handled;
    }

    /**
     * Prepare list to be used.
     * @param Request $request Request to resolve.
     * @return Lister
     * @throws ListerException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Dszczer\ListerBundle\Filter\FilterException
     * @throws \Dszczer\ListerBundle\Sorter\SorterException
     */
    public function apply(Request $request): Lister
    {
        $this->customOptions = $this->resolveCustomOptions($this->customOptions);

        if (!$this->buildFilterForm() && $this->filters->count() > 0) {
            throw new ListerException('Cannot apply list when filters are defined and form is not built.');
        } elseif (!$this->elements->count()) {
            throw new ListerException('Cannot apply list when there is no defined Elements.');
        } elseif (!$this->query) {
            throw new ListerException('Cannot apply list without reference to ORM.');
        }

        $this->handleRequest($request);
        if ($this->rebuildQuery) {
            if ($this->externalQuery) {
                $this->query = clone $this->externalQuery;
            } else {
                if ($this->query instanceof ModelCriteria) {
                    $this->query->clear();
                }
                if ($this->query instanceof QueryBuilder) {
                    $this->query = $this->repository->createQueryBuilder('e');
                }
            }
            $this->rebuildQuery = false;
        }

        /** @var Filter $filter */
        foreach ($this->filters->all() as $filter) {
            $val = $filter->getValue();
            $extraArgs = [];
            if ($filter->isDefaultMethod() && $filter->getType(false) == Filter::TYPE_TEXT && $val) {
                $extraArgs = $this->query instanceof ModelCriteria ? [' LIKE '] : ['LIKE'];
                if (strpos($val, '*') === false) {
                    $filter->setValue("%$val%");
                } else {
                    $filter->setValue(str_replace('*', '%', $val));
                }
            }
            $filter->apply($this, $extraArgs);
            $filter->setValue($val);
        }
        /** @var Sorter $sorter */
        foreach ($this->sorters->all() as $sorter) {
            $sorter->apply($this);
        }
        if ($this->query instanceof ModelCriteria) {
            $this->pager = $this->getQuery(false)->paginate($this->currentPage, $this->perPage);
        } else {
            $this->pager = new PagerHelper($this->query, $this->getPerPage());
            $this->pager->setPage($this->getCurrentPage());
            $this->pager->init();
        }
        $this->currentPage = max(1, $this->pager->getPage());

        if ($request->getSession() instanceof SessionInterface) {
            $this->storeInSession($request->getSession());
        }

        return $this;
    }

    /**
     * Get elements with stored data in them, ready to be displayed.
     * @param mixed $data
     * @return Element[]|array
     */
    public function getHydratedElements($data = null): array
    {
        $hydrated = [];
        if ($data === null) {
            foreach ($this->getPager()->getResults() as $row) {
                $hydrated[] = $this->getHydratedElements($row);
            }
        } else {
            foreach ($this->getElements() as $element) {
                $hydrated[] = $this->prepareElement($element, $data);
            }
        }

        return $hydrated;
    }

    /**
     * Store data in single Element.
     * @param Element $element
     * @param mixed $data
     * @return Element
     */
    protected function prepareElement(Element $element, $data): Element
    {
        $detached = clone $element;
        $detached->setData($data);

        return $detached;
    }

    /**
     * Can Lister object be serialized?
     * @return bool
     */
    public function isSerializable(): bool
    {
        /** @var Element $element */
        foreach($this->elements->all() as $element) {
            if($element->isCustom()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get array representation of object.
     * @return array
     * @throws UnserializableException
     */
    public function serialize(): array
    {
        if(!$this->isSerializable()) {
            throw new UnserializableException(
                "Cannot serialize Lister because of anonymous functions/classes in Element instances"
            );
        }

        return [
            'id' => $this->id,
            'query' => $this->externalQuery instanceof ModelCriteria ? Helper::encodeAnything($this->query) : null,
            'externalQuery' => $this->externalQuery instanceof ModelCriteria ? Helper::encodeAnything($this->externalQuery) : null,
            'filters' => Helper::encodeAnything($this->filters),
            'sorters' => Helper::encodeAnything($this->sorters),
            'elements' => Helper::encodeAnything($this->elements),
            'perpage' => $this->perPage,
            'currentPage' => $this->currentPage,
            'persist' => $this->persist,
            'dynamic' => $this->dynamic,
            'filterLayout' => $this->filterLayout,
            'listLayout' => $this->listLayout,
            'elementLayout' => $this->elementLayout,
            'paginationLayout' => $this->paginationLayout,
            'translationDomain' => $this->translationDomain,
            'customOptions' => $this->customOptions,
        ];
    }

    /**
     * Restore array serialized object into self.
     * @param array $data Serialized data
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function unserialize(array $data)
    {
        if (!is_array($data) || empty($data)) {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['id']) && is_string($data['id'])) {
            $this->id = $data['id'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string. ID does not match.');
        }
        if (isset($data['query']) && is_string($data['query'])) {
            $objectOrNull = Helper::decodeAnything($data['query']);
            if ($objectOrNull instanceof ModelCriteria || $objectOrNull === null) {
                $this->query = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string. Query object is not valid.');
            }
        }
        if (isset($data['externalQuery']) && is_string($data['externalQuery'])) {
            $objectOrNull = Helper::decodeAnything($data['externalQuery']);
            if ($objectOrNull instanceof ModelCriteria || $objectOrNull === null) {
                $this->externalQuery = $objectOrNull;
            } else {
                throw new \InvalidArgumentException(
                    'This is not Lister serialized string. External query object is not valid.'
                );
            }
        }
        if (isset($data['filters']) && is_string($data['filters'])) {
            $objectOrNull = Helper::decodeAnything($data['filters']);
            if ($objectOrNull instanceof FilterBag || $objectOrNull === null) {
                $this->filters = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string. Filter bag is not valid.');
            }
        }
        if (isset($data['sorters']) && is_string($data['sorters'])) {
            $objectOrNull = Helper::decodeAnything($data['sorters']);
            if ($objectOrNull instanceof SorterBag || $objectOrNull === null) {
                $this->sorters = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string. Sorter bag is not valid.');
            }
        }
        if (isset($data['elements']) && is_string($data['elements'])) {
            $objectOrNull = Helper::decodeAnything($data['elements']);
            if ($objectOrNull instanceof ElementBag || $objectOrNull === null) {
                $this->elements = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string. Element bag is not valid.');
            }
        }
        if (isset($data['perpage']) && is_int($data['perpage'])) {
            $this->perPage = $data['perpage'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "perpage" property.'
            );
        }
        if (isset($data['currentPage']) && is_int($data['currentPage'])) {
            $this->currentPage = $data['currentPage'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "currentPage" property.'
            );
        }
        if (isset($data['persist']) && is_bool($data['persist'])) {
            $this->persist = $data['persist'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "persist" property.'
            );
        }
        if (isset($data['dynamic']) && is_bool($data['dynamic'])) {
            $this->dynamic = $data['dynamic'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "dynamic" property.'
            );
        }
        if (isset($data['filterLayout']) && is_string($data['filterLayout'])) {
            $this->filterLayout = $data['filterLayout'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "filterLayout" property.'
            );
        }
        if (isset($data['listLayout']) && is_string($data['listLayout'])) {
            $this->listLayout = $data['listLayout'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "listLayout" property.'
            );
        }
        if (isset($data['elementLayout']) && is_string($data['elementLayout'])) {
            $this->elementLayout = $data['elementLayout'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "elementLayout" property.'
            );
        }
        if (isset($data['paginationLayout']) && is_string($data['paginationLayout'])) {
            $this->paginationLayout = $data['paginationLayout'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "paginationLayout" property.'
            );
        }
        if (isset($data['translationDomain']) && is_string($data['translationDomain'])) {
            $this->translationDomain = $data['translationDomain'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "translationDomain" property.'
            );
        }
        if (isset($data['customOptions']) && is_array($data['customOptions'])) {
            $this->customOptions = $data['customOptions'];
        } else {
            throw new \InvalidArgumentException(
                'This is not Lister serialized string. Missing or invalid "customOptions" property.'
            );
        }
        $this->rebuildQuery = true;
    }

    /**
     * Don't use this.
     * @deprecated Restricted use of __sleep()
     * @throws \BadMethodCallException
     */
    public final function __sleep()
    {
        throw new \BadMethodCallException('This object can be serialized only with "serialize" method');
    }

    /**
     * Don't use this.
     * @deprecated Restricted use of __wakeup()
     * @throws \BadMethodCallException
     */
    public final function __wakeup()
    {
        throw new \BadMethodCallException('This object can be unserialized only with "unserialize" method');
    }
}