<?php

/**
 * Chrome vocabulary of the new NewCity/Tailwind design, taken from the
 * compiled theme (/_resources/_theme/tailwind.css) and the pages published on
 * www-dev. The compiled CSS only contains the utility classes the site's own
 * templates use, so every utility named here was checked against that build;
 * anything the theme does not style (form controls, data tables) is handled
 * by our own small stylesheet under body.majors-new instead.
 *
 * Design-system components used directly: .nc-heading (headings), .nc-button
 * (+ .nc-button-text inner span for the arrow), .nc-fancy-link-list /
 * .nc-fancy-link (link lists), data-tw-theme bands, .container, .prose.
 */

declare(strict_types=1);

return [
    'main'                 => 'relative pb-px -mb-px grow flex flex-col',
    'wrapper'              => 'flex flex-col gap-6',

    'page_header'          => '',
    'page_header.hero'     => '',
    'page_header.bar'      => 'container',
    'page_header.title'    => '',
    'headline'             => 'nc-heading',
    'headline.head'        => '',
    'headline.super'       => 'block text-sm font-bold uppercase tracking-wide text-neutral-500 mb-1',
    'section_nav'          => '',
    'section_nav.toggle'   => 'sidebar-up:hidden',
    'section_nav.wrapper'  => '',
    'section_nav.button'   => 'nc-button',

    // Vertical rhythm inside the main column comes from the theme's own class.
    'section'              => 'vertical-rhythm-standard',
    'section.shade'        => 'vertical-rhythm-standard majors-band',
    'section.feature'      => 'vertical-rhythm-standard',
    'section.actions'      => 'vertical-rhythm-standard majors-band',
    'section.header'       => 'mb-4',
    'landing_panel'        => 'prose max-w-4xl',
    'landing_panel.text'   => 'text-lg',
    'landing_panel.quick'  => '',
    'landing_panel.headline' => 'nc-heading text-xl mb-3',
    'landing_panel.buttons'  => 'flex flex-wrap items-center gap-3',

    'filters'              => 'flex flex-wrap items-end gap-4',
    'filters.search'       => 'flex items-stretch grow',
    'filters.select'       => 'flex flex-col',

    'button'               => 'nc-button',
    'button.accent'        => 'nc-button majors-accent',
    'button.subtle'        => 'nc-button majors-subtle',
    'button.small'         => 'nc-chip-link',
    'button_collection'    => 'flex flex-wrap items-center gap-3',

    // The theme styles tables only inside .prose (dark head row, bordered cells,
    // zebra rows); max-w-none lifts prose's 65ch limit so the table can fill the column.
    'table'                => '',
    'table_wrap'           => 'prose max-w-none overflow-x-auto',

    'alpha_list'           => '',
    'alpha_list.items'     => 'mt-8',
    'divided_list'         => '',
    'divided_list.item'    => 'py-3',

    'alert'                => 'majors-note',
    'alert.emergency'      => 'majors-note majors-note--danger',
    'alert.wrapper'        => 'flex gap-3',
    'alert.icon'           => 'shrink-0 w-6',
    'alert.message'        => 'grow',

    'row'                  => 'flex flex-wrap gap-4',
    'col.2'                => 'basis-1/6',
    'col.10'               => 'basis-5/6 grow',
    'col.6'                => 'basis-1/2 grow',
    'col.12'               => 'basis-full',

    'heading3'             => 'nc-heading text-2xl',
    'heading4'             => 'nc-heading text-xl',
    'heading5'             => 'nc-heading text-lg',
    'heading6'             => 'nc-heading text-base',
    'sr_only'              => 'sr-only',
    'icon'                 => 'inline-block w-5 h-5',
    'link.rich'            => 'nc-fancy-link',
];
