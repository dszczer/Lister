# Lister Bundle

[![Build Status](https://travis-ci.org/dszczer/Lister.svg?branch=master)](https://travis-ci.org/dszczer/Lister)

###### Disclaimer: documentation and test suit is not complete. This bundle is in development stage.

## Requirements

### PHP >7.0.0

### Symfony ~2.8
#### (Symfony ~3.0 not tested, may work)

### Doctrine ORM ~2.0 or Propel ORM ~2.0
#### (Propel ~3.0 not tested)

## Installation

1. Copy files via Composer: `composer require dszczer/lister`.
2. Place configuration if needed:
```yaml
# app/config.yml

# Full Lister Configuration
lister:
    orm: auto # [auto, doctrine, propel]; 'auto' for auto detection, and using both of ORMs simultaneously also
    perpage: 25 # any integer above 0
    form_name_prefix: 'lister_filters'
    use_csrf: true
    
# Minimum Lister Configuration
# Above values are default ones, no need to place any configuarion node
```
3. Add routing:
```yaml
# app/config/routing.yml
lister:
    resource: "@DszczerListerBundle/Resources/config/routing.yml"
    type:     yaml
```
4. Enable bundle in AppKernel:
```php
// app/AppKernel.php
// ...
$bundles = [
    // ...
    new Dszczer\ListerBundle\DszczerListerBundle()
];

// ...
```

## Basic usage
```php
<?php
// src/AppBundle/Controller/AppController.php

public function listAction(Request $request)
{
    // use factory to create new lister
    $list = $this->get('lister.factory')->createList(
        '\\Full\\Class\\Name\\Of\\ModelCriteria\\Query\\Object', // full class name of Propel query object
        'exampleOneList', // unique list identifier
        'lister' // translation domain
    );
    
    // create some basic list
    // NOTICE: order of adding items does matter!
    $list
        ->addField('id', 'Id')
        ->addField('username', 'Username', true, Filter::TYPE_TEXT)
        ->addField('email', 'E-mail', true, Filter::TYPE_TEXT);
    
    return $this->render(
        'AppBundle:User:list.html.twig',
        ['list' => $list->apply($request)]
    );
}
```
```twig
{# src/AppBundle/Resources/views/User/list.html.twig #}

{{ lister_filters(list) }}
{{ lister_body(list) }}
{{ lister_pagination(list) }}
```