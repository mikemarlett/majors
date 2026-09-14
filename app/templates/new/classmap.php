<?php

/**
 * Chrome vocabulary of the new NewCity/Tailwind design (www-dev).
 *
 * First pass: Tailwind utilities that match the design system's spacing and
 * type scale as used in the site's own templates (container, nc-heading,
 * data-tw-theme bands). Refine against the live _theme/includes on www-dev
 * (plan step 8) — everything design-specific is confined to this file, the
 * layout, and templates/new/partials/.
 */

declare(strict_types=1);

return [
    'main'                 => 'majors-main',
    'wrapper'              => 'container my-8 flex flex-col gap-6',

    'page_header'          => 'majors-page-header',
    'page_header.hero'     => 'majors-page-header',
    'page_header.bar'      => 'container py-6 flex flex-wrap items-center justify-between gap-4',
    'page_header.title'    => '',
    'headline'             => 'nc-heading',
    'headline.head'        => '',
    'headline.super'       => 'block text-sm uppercase tracking-wide text-neutral-600',
    'section_nav'          => 'majors-section-nav',
    'section_nav.toggle'   => 'md:hidden',
    'section_nav.wrapper'  => '',
    'section_nav.button'   => 'inline-flex items-center gap-2 border border-neutral-400 px-3 py-2',

    'section'              => 'container my-8',
    'section.shade'        => 'bg-neutral-100 py-4',
    'section.feature'      => 'container my-6',
    'section.actions'      => 'bg-neutral-100 py-4',
    'section.header'       => 'mb-4',
    'landing_panel'        => 'max-w-4xl',
    'landing_panel.text'   => 'text-lg leading-relaxed',
    'landing_panel.quick'  => 'container',
    'landing_panel.headline' => 'nc-heading text-xl mb-3',
    'landing_panel.buttons'  => 'flex flex-wrap gap-3',

    'filters'              => 'container flex flex-wrap items-end gap-4',
    'filters.search'       => 'flex items-stretch gap-2 grow',
    'filters.select'       => 'flex flex-col',

    'button'               => 'inline-flex items-center gap-2 border border-neutral-900 bg-neutral-900 text-white px-4 py-2 font-semibold hover:bg-neutral-700',
    'button.accent'        => 'inline-flex items-center gap-2 bg-[#ffc217] text-neutral-900 px-4 py-2 font-semibold hover:bg-[#f3ad1c]',
    'button.subtle'        => 'inline-flex items-center gap-2 border border-neutral-400 px-4 py-2 hover:bg-neutral-100',
    'button.small'         => 'inline-flex items-center gap-1 border border-neutral-400 px-2 py-1 text-sm hover:bg-neutral-100',
    'button_collection'    => 'flex flex-wrap gap-3',

    'table'                => 'w-full border-collapse text-sm',
    'table_wrap'           => 'overflow-x-auto',

    'alpha_list'           => 'majors-alpha-list',
    'alpha_list.items'     => 'mt-6',
    'divided_list'         => 'divide-y divide-neutral-200',
    'divided_list.item'    => 'py-3',

    'alert'                => 'border-l-4 border-[#ffc217] bg-neutral-50 p-4 my-4',
    'alert.emergency'      => 'border-l-4 border-red-700 bg-red-50 p-4 my-4',
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
    'link.rich'            => 'underline font-semibold',
];
