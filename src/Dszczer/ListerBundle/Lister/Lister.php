<?php
/**
 * Lister class representation.
 * @category     Lister
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Lister;

use Dszczer\ListerBundle\Element\Element;
use Dszczer\ListerBundle\Element\ElementBag;
use Dszczer\ListerBundle\Filter\Filter;
use Dszczer\ListerBundle\Filter\FilterBag;
use Dszczer\ListerBundle\Sorter\Sorter;
use Dszczer\ListerBundle\Sorter\SorterBag;
use Dszczer\ListerBundle\Util\Helper;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Util\PropelModelPager;
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
 */
class Lister
{
    /** string key used to store all lists in session */
    const SERIALIZED_STORAGE_NAME = 'lister_serialized_objects';

    /** @var  string UUID */
    protected $id = '';
    /** @var  ModelCriteria Query to use when applying list */
    protected $query;
    /** @var  ModelCriteria|null Query from external source */
    protected $externalQuery;
    /** @var  FilterBag Bag of filters */
    protected $filters;
    /** @var  SorterBag Bag of sorters */
    protected $sorters;
    /** @var  ElementBag Bag of elements */
    protected $elements;
    /** @var  PropelModelPager Pagination helper */
    protected $pager;
    /** @var  int Quantity of rows per page */
    protected $perpage = 0;
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
     * @param string $id Unique identifier
     * @param string $modelClass Full class name of ModelCriteria's child object
     */
    public function __construct($id = '', $modelClass = '')
    {
        if (!empty($modelClass)) {
            $query = new $modelClass();
            if (!$query instanceof ModelCriteria) {
                throw new \InvalidArgumentException('Class is not an instance of ModelCriteria');
            }
            unset($query);
            $this->query = call_user_func($modelClass . '::create');
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
     */
    public static function getFromSession(SessionInterface $session, $uuid)
    {
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
     */
    public static function removeFromSession(SessionInterface $session, $uuid)
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
    public function storeInSession(SessionInterface $session, $overwrite = true)
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
    public function isDynamic()
    {
        return $this->dynamic;
    }

    /**
     * True to use dynamic controls, false for static.
     * @param bool $dynamic
     * @return Lister
     */
    public function setDynamic($dynamic)
    {
        $this->dynamic = $dynamic;

        return $this;
    }

    /**
     * Get Propel pagination helper or null if list was not applied yet.
     * @return PropelModelPager|null
     */
    public function getPager()
    {
        return $this->pager;
    }

    /**
     * Set external query object to use as base for filtering, paginating and sorting.
     * @param ModelCriteria $query |null
     * @return Lister
     */
    public function setQuery(ModelCriteria $query)
    {
        $this->query = $query;
        $this->externalQuery = clone $query;

        return $this;
    }

    /**
     * Get external query object if set.
     * @param bool $clone True for cloned or false for original model criteria
     * @return ModelCriteria|null
     */
    public function getQuery($clone = true)
    {
        return $clone && $this->query instanceof ModelCriteria ? clone $this->query : $this->query;
    }

    /**
     * Set quantity of Elements per one page.
     * @param int $int
     * @return Lister
     */
    public function setPerPage($int)
    {
        $this->perpage = max(0, $int);

        return $this;
    }

    /**
     * Get quantity of ELements per one page.
     * @return int
     */
    public function getPerPage()
    {
        return $this->perpage;
    }

    /**
     * True to allow, false to disallow storing in session.
     * @param bool $state
     * @return Lister
     */
    public function setPersist($state)
    {
        $this->persist = $state;

        return $this;
    }

    /**
     * Check if list can be stored in session.
     * @return bool
     */
    public function isPersist()
    {
        return $this->persist;
    }

    /**
     * Get current displayed page.
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Get current displayed page HTTP GET parameter's name.
     * @return string
     */
    public function getPageRequestParameterName()
    {
        return 'p_' . $this->getId();
    }

    /**
     * Get id of list.
     * @return string
     */
    public function getId()
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
     */
    public function addField(
        $name,
        $label,
        $sort = false,
        $filterType = '',
        $filterMethod = '',
        $filterValue = null,
        array $filterValues = [],
        $sorterMethod = '',
        $sorterValue = null,
        $elementMethod = '',
        $elementCallable = null,
        $elementData = null
    )
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
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->filters->all();
    }

    /**
     * Get filter by it's name.
     * @param string $name
     * @return Filter|null
     */
    public function getFilter($name)
    {
        return $this->filters->get($name);
    }

    /**
     * Replace filters with new ones.
     * @param FilterBag $filters
     * @param bool $overwrite True for overwrite, false for merge (existing filters wll not be modified)
     * @return Lister
     */
    public function setFilters(FilterBag $filters, $overwrite = false)
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
    public function addFilter(Filter $filter, $overwrite = false)
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
    public function hasFilter($name)
    {
        return $this->filters->has($name instanceof Filter ? $name->getName() : $name);
    }

    /**
     * Remove filter from list.
     * @param string $name
     * @return Lister
     */
    public function removeFilter($name)
    {
        $this->filters->remove($name);

        return $this;
    }

    /**
     * Get array of sorters.
     * @return Sorter[]
     */
    public function getSorters()
    {
        return $this->sorters->all();
    }

    /**
     * Get sorter by it's name.
     * @param string $name
     * @return Sorter|null
     */
    public function getSorter($name)
    {
        return $this->sorters->get($name);
    }

    /**
     * Replace sorters with new ones.
     * @param SorterBag $sorters
     * @param bool $overwrite True for overwrite, false for merge (existing sorters will not be modified)
     * @return Lister
     */
    public function setSorters(SorterBag $sorters, $overwrite = false)
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
    public function addSorter(Sorter $sorter, $overwrite = false)
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
    public function hasSorter($name)
    {
        return $this->sorters->has($name instanceof Sorter ? $name->getName() : $name);
    }

    /**
     * Remove sorter from list.
     * @param string $name
     * @return Lister
     */
    public function removeSorter($name)
    {
        $this->sorters->remove($name);

        return $this;
    }

    /**
     * Get array of elements.
     * @return Element[]
     */
    public function getElements()
    {
        return $this->elements->all();
    }

    /**
     * Get element by it's name.
     * @param string $name
     * @return Element|null
     */
    public function getElement($name)
    {
        return $this->elements->get($name);
    }

    /**
     * Replave elements with new ones.
     * @param ElementBag $elements
     * @param bool $overwrite True for replace, false for merge (existing elements won't be modified)
     * @return Lister
     */
    public function setElements(ElementBag $elements, $overwrite = false)
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
    public function addElement(Element $element, $overwrite = false)
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
    public function hasElement($name)
    {
        return $this->elements->has($name);
    }

    /**
     * Remove element from list.
     * @param string $name
     * @return Lister
     */
    public function removeElement($name)
    {
        $this->elements->remove($name);

        return $this;
    }

    /**
     * Get filter form display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getFilterLayout($raw = false)
    {
        return $raw ? $this->filterLayout : Helper::fixTwigTemplatePath($this->filterLayout);
    }

    /**
     * Set filter form display layout twig path.
     * @param string $filterLayout Twig path to file.
     * @return Lister
     */
    public function setFilterLayout($filterLayout)
    {
        $this->filterLayout = $filterLayout;

        return $this;
    }

    /**
     * Get list display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getListLayout($raw = false)
    {
        return $raw ? $this->listLayout : Helper::fixTwigTemplatePath($this->listLayout);
    }

    /**
     * Set list display layout twig path.
     * @param string $listLayout
     * @return Lister
     */
    public function setListLayout($listLayout)
    {
        $this->listLayout = $listLayout;

        return $this;
    }

    /**
     * Get element display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getElementLayout($raw = false)
    {
        return $raw ? $this->elementLayout : Helper::fixTwigTemplatePath($this->elementLayout);
    }

    /**
     * Set element display layout twig path.
     * @param string $elementLayout
     * @return Lister
     */
    public function setElementLayout($elementLayout)
    {
        $this->elementLayout = $elementLayout;

        return $this;
    }

    /**
     * Get pagination display layout twig path to file.
     * @param bool $raw Raw for bypass stored value, false for fixed Twig path.
     * @return string
     */
    public function getPaginationLayout($raw = false)
    {
        return $raw ? $this->paginationLayout : Helper::fixTwigTemplatePath($this->paginationLayout);
    }

    /**
     * Set pagination display layout twig path.
     * @param string $paginationLayout
     * @return Lister
     */
    public function setPaginationLayout($paginationLayout)
    {
        $this->paginationLayout = $paginationLayout;

        return $this;
    }

    /**
     * Get translation domain.
     * @return string
     */
    public function getTranslationDomain()
    {
        return $this->translationDomain;
    }

    /**
     * Set translation domain.
     * @param string $domain
     * @return Lister
     */
    public function setTranslationDomain($domain)
    {
        $this->translationDomain = $domain;

        return $this;
    }

    /**
     * Get user defined options.
     * @return array
     */
    public function getCustomOptions()
    {
        return $this->customOptions;
    }

    /**
     * Set user defined options.
     * @param array $customOptions
     * @return Lister
     */
    public function setCustomOptions(array $customOptions)
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
    protected function resolveCustomOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                'maxLinks' => 7,
                'route' => 'lister_quick_reload',
                'params' => ['uuid' => $this->id]
            ])
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
    public function setFilterFormBuilder(FormBuilder $filterForm)
    {
        $this->filterForm = $filterForm;

        return $this;
    }

    /**
     * Builds filter form to manipulate list.
     * @return bool True on successful build, false on fail
     */
    protected function buildFilterForm()
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
                throw new ListerException('Cannot build sorter form because ' . $sorter . ' does not exist.');
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
    public function setSorterFormBuilder(FormBuilder $builder)
    {
        $this->sorterFormBuilder = $builder;

        return $this;
    }

    /**
     * Build sorter form.
     * @param Sorter $sorter
     * @return Form
     */
    protected function buildSorterForm(Sorter $sorter)
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
            case Criteria::ASC:
                $class = 'sort-asc';
                break;
            case Criteria::DESC:
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
    public function setRouter(Router $router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Handle request to resolve filtering, sorting and pagination.
     * @param Request $request
     * @return bool True on handled request, false on failure
     */
    public function handleRequest(Request $request)
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
                        ? Criteria::ASC
                        : ($clickedSorter->getValue() == Criteria::ASC ? Criteria::DESC : Criteria::ASC);
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
     */
    public function apply(Request $request)
    {
        $this->customOptions = $this->resolveCustomOptions($this->customOptions);

        if (!$this->buildFilterForm() && $this->filters->count() > 0) {
            throw new ListerException('Cannot apply list when filters are defined and form is not built.');
        } elseif (!$this->elements->count()) {
            throw new ListerException('Cannot apply list when there is no defined Elements.');
        } elseif (!$this->query instanceof ModelCriteria) {
            throw new ListerException('Cannot apply list without ModelCriteria query object.');
        }

        $this->handleRequest($request);
        if ($this->rebuildQuery) {
            if ($this->externalQuery instanceof ModelCriteria) {
                $this->query = clone $this->externalQuery;
            } else {
                $this->query->clear();
            }
            $this->rebuildQuery = false;
        }

        /** @var Filter $filter */
        foreach ($this->filters->all() as $filter) {
            $val = $filter->getValue();
            if ($filter->isDefaultMethod() && $filter->getType(false) == Filter::TYPE_TEXT && $val) {
                if (strpos($val, '*') === false) {
                    $filter->setValue("%$val%");
                    $filter->apply($this, [Criteria::LIKE]);
                    $filter->setValue(str_replace('%', '', $filter->getValue()));
                } else {
                    $filter->setValue(str_replace('*', '%', $val));
                    $filter->apply($this, [Criteria::LIKE]);
                    $filter->setValue(str_replace('%', '*', $filter->getValue()));
                }
            } else {
                $filter->apply($this);
            }
        }
        /** @var Sorter $sorter */
        foreach ($this->sorters->all() as $sorter) {
            $sorter->apply($this);
        }
        $this->pager = $this->getQuery(false)->paginate($this->currentPage, $this->perpage);
        $this->currentPage = max(1, $this->pager->getPage());

        if ($request->getSession() instanceof SessionInterface) {
            $this->storeInSession($request->getSession());
        }

        return $this;
    }

    /**
     * Get elements with stored data in them, ready to be displayed.
     * @param mixed $data
     * @return Element[]
     */
    public function getHydratedElements($data = null)
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
    protected function prepareElement(Element $element, $data)
    {
        $detached = clone $element;
        $detached->setData($data);

        return $detached;
    }

    /**
     * Get array representation of object.
     * @return array
     */
    public function serialize()
    {
        return [
            'id' => $this->id,
            'query' => Helper::encodeAnything($this->query),
            'externalQuery' => Helper::encodeAnything($this->externalQuery),
            'filters' => Helper::encodeAnything($this->filters),
            'sorters' => Helper::encodeAnything($this->sorters),
            'elements' => Helper::encodeAnything($this->elements),
            'perpage' => $this->perpage,
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
     */
    public function unserialize(array $data)
    {
        if (!is_array($data) || empty($data)) {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['id']) && is_string($data['id'])) {
            $this->id = $data['id'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['query']) && is_string($data['query'])) {
            $objectOrNull = Helper::decodeAnything($data['query']);
            if ($objectOrNull instanceof ModelCriteria || $objectOrNull === null) {
                $this->query = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string');
            }
        }
        if (isset($data['externalQuery']) && is_string($data['externalQuery'])) {
            $objectOrNull = Helper::decodeAnything($data['externalQuery']);
            if ($objectOrNull instanceof ModelCriteria || $objectOrNull === null) {
                $this->externalQuery = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string');
            }
        }
        if (isset($data['filters']) && is_string($data['filters'])) {
            $objectOrNull = Helper::decodeAnything($data['filters']);
            if ($objectOrNull instanceof FilterBag || $objectOrNull === null) {
                $this->filters = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string');
            }
        }
        if (isset($data['sorters']) && is_string($data['sorters'])) {
            $objectOrNull = Helper::decodeAnything($data['sorters']);
            if ($objectOrNull instanceof SorterBag || $objectOrNull === null) {
                $this->sorters = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string');
            }
        }
        if (isset($data['elements']) && is_string($data['elements'])) {
            $objectOrNull = Helper::decodeAnything($data['elements']);
            if ($objectOrNull instanceof ElementBag || $objectOrNull === null) {
                $this->elements = $objectOrNull;
            } else {
                throw new \InvalidArgumentException('This is not Lister serialized string');
            }
        }
        if (isset($data['perpage']) && is_int($data['perpage'])) {
            $this->perpage = $data['perpage'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['currentPage']) && is_int($data['currentPage'])) {
            $this->currentPage = $data['currentPage'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['persist']) && is_bool($data['persist'])) {
            $this->persist = $data['persist'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['dynamic']) && is_bool($data['dynamic'])) {
            $this->dynamic = $data['dynamic'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['filterLayout']) && is_string($data['filterLayout'])) {
            $this->filterLayout = $data['filterLayout'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['listLayout']) && is_string($data['listLayout'])) {
            $this->listLayout = $data['listLayout'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['elementLayout']) && is_string($data['elementLayout'])) {
            $this->elementLayout = $data['elementLayout'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['paginationLayout']) && is_string($data['paginationLayout'])) {
            $this->paginationLayout = $data['paginationLayout'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['translationDomain']) && is_string($data['translationDomain'])) {
            $this->translationDomain = $data['translationDomain'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
        }
        if (isset($data['customOptions']) && is_array($data['customOptions'])) {
            $this->customOptions = $data['customOptions'];
        } else {
            throw new \InvalidArgumentException('This is not Lister serialized string');
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