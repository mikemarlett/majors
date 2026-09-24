<?php

/**
 * Chrome vocabulary of the current wichita.edu design (Foundation/BEM).
 * Shared templates ask for these tokens through $t->cls('token'); this file
 * is the only place the old design's class names live.
 */

declare(strict_types=1);

return [
    'main'                 => 'main main--slab',
    'wrapper'              => 'main-wrapper',

    'page_header'          => 'page-header',
    'page_header.hero'     => 'page-header page-header--hero',
    'page_header.bar'      => 'page-header__bar',
    'page_header.title'    => 'page-header__page-title',
    'headline'             => 'headline-group',
    'headline.head'        => 'head',
    'headline.super'       => 'superhead',
    'section_nav'          => 'section-nav',
    'section_nav.toggle'   => 'section-nav__toggle',
    'section_nav.wrapper'  => 'section-nav__toggle-wrapper',
    'section_nav.button'   => 'toggleSectionNav primary-toggle',

    'section'              => 'section-wrap',
    'section.shade'        => 'section-wrap section-wrap--shade-light section-wrap--short',
    'section.feature'      => 'section-wrap section-wrap--arrows-bright',
    'section.actions'      => 'section-wrap section-wrap--arrows-dark section-wrap--short',
    'section.header'       => 'section-header',
    'landing_panel'        => 'landing-panel landing-panel--feature',
    'landing_panel.text'   => 'landing-panel__text',
    'landing_panel.quick'  => 'landing-panel landing-panel--quicklink',
    'landing_panel.headline' => 'landing-panel__headline',
    'landing_panel.buttons'  => 'button_collection landing-panel__buttons',

    'filters'              => 'search-filters',
    'filters.search'       => 'search-filters__search',
    'filters.select'       => 'search-filters__select',

    'button'               => 'button',
    'button.accent'        => 'button button--accent',
    'button.subtle'        => 'button button--subtle',
    'button.small'         => 'button button--small',
    'button_collection'    => 'button-collection',

    'table'                => 'table--zebra-stripe',
    'table_wrap'           => 'table-responsive-wrap',

    'alpha_list'           => 'alpha-list',
    'alpha_list.items'     => 'alpha-list__items',
    'divided_list'         => 'divided-list',
    'divided_list.item'    => 'divided-list__item',

    'prose'                => '',
    'alert'                => 'alert-bar',
    'alert.emergency'      => 'alert-bar alert-bar--emergency',
    'alert.wrapper'        => 'alert-bar__wrapper',
    'alert.icon'           => 'alert-bar__icon',
    'alert.message'        => 'alert-bar__message',

    'row'                  => 'row',
    'col.2'                => 'col-2',
    'col.10'               => 'col-10',
    'col.6'                => 'col-6',
    'col.12'               => 'col-12',

    'heading3'             => 'heading3',
    'heading4'             => 'heading4',
    'heading5'             => 'heading5',
    'heading6'             => 'heading6',
    'sr_only'              => 'show-for-sr',
    'icon'                 => 'icon',
    'link.rich'            => 'link--rich',
];
