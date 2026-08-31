@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('Nawigacja po stronach') }}" class="flex items-center justify-between">
            <div class="flex justify-between flex-1 sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-200 cursor-default leading-5 dark:bg-neutral-900 dark:border-neutral-700 dark:text-zinc-300 dark:active:bg-neutral-800 dark:active:text-zinc-200">
                            {!! __('pagination.previous') !!}
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-zinc-700 bg-white border border-zinc-200 leading-5 hover:text-zinc-900 focus:outline-none focus:z-10 focus:ring-2 focus:ring-brand-500 active:bg-zinc-100 active:text-zinc-800 transition ease-in-out duration-150 dark:bg-neutral-900 dark:border-neutral-700 dark:text-zinc-300 dark:active:bg-neutral-800 dark:active:text-zinc-200">
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-zinc-700 bg-white border border-zinc-200 leading-5 hover:text-zinc-900 focus:outline-none focus:z-10 focus:ring-2 focus:ring-brand-500 active:bg-zinc-100 active:text-zinc-800 transition ease-in-out duration-150 dark:bg-neutral-900 dark:border-neutral-700 dark:text-zinc-300 dark:active:bg-neutral-800 dark:active:text-zinc-200">
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-zinc-500 bg-white border border-zinc-200 cursor-default leading-5 dark:text-zinc-500 dark:bg-neutral-900 dark:border-neutral-700">
                            {!! __('pagination.next') !!}
                        </span>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    {{--
                        Oryginał składa to zdanie z czterech osobnych kluczy
                        (`Showing`, `to`, `of`, `results`), czego nie da się
                        ułożyć po polsku: liczba domyka zdanie, a Laravel
                        ignoruje tłumaczenia będące pustym ciągiem i wstawia
                        z powrotem angielski klucz. Stąd jedno zdanie
                        z podstawieniami.
                    --}}
                    <p class="text-sm text-zinc-600 leading-5 dark:text-zinc-400">
                        {!! __('Wyniki od :first do :last z :total', [
                            'first' => '<span class="font-medium">'.$paginator->firstItem().'</span>',
                            'last' => '<span class="font-medium">'.$paginator->lastItem().'</span>',
                            'total' => '<span class="font-medium">'.$paginator->total().'</span>',
                        ]) !!}
                    </p>
                </div>

                <div>
                    <span class="relative inline-flex rtl:flex-row-reverse shadow-sm">
                        <span>
                            {{-- Previous Page Link --}}
                            @if ($paginator->onFirstPage())
                                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                                    <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-200 cursor-default leading-5 dark:bg-neutral-900 dark:border-neutral-700" aria-hidden="true">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-200 leading-5 hover:text-zinc-700 focus:outline-none focus:z-10 focus:ring-2 focus:ring-brand-500 active:bg-zinc-100 active:text-zinc-600 transition ease-in-out duration-150 dark:bg-neutral-900 dark:border-neutral-700 dark:active:bg-neutral-800" aria-label="{{ __('pagination.previous') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>

                        {{-- Pagination Elements --}}
                        @foreach ($elements as $element)
                            {{-- "Three Dots" Separator --}}
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-700 bg-white border border-zinc-200 cursor-default leading-5 dark:bg-neutral-900 dark:border-neutral-700 dark:text-zinc-300">{{ $element }}</span>
                                </span>
                            @endif

                            {{-- Array Of Links --}}
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-500 bg-white border border-zinc-200 cursor-default leading-5 dark:bg-neutral-900 dark:border-neutral-700">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-zinc-700 bg-white border border-zinc-200 leading-5 hover:text-zinc-900 focus:outline-none focus:z-10 focus:ring-2 focus:ring-brand-500 active:bg-zinc-100 active:text-zinc-800 transition ease-in-out duration-150 dark:bg-neutral-900 dark:border-neutral-700 dark:text-zinc-400 dark:hover:text-zinc-200 dark:active:bg-neutral-800" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            {{-- Next Page Link --}}
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-zinc-500 bg-white border border-zinc-200 leading-5 hover:text-zinc-700 focus:outline-none focus:z-10 focus:ring-2 focus:ring-brand-500 active:bg-zinc-100 active:text-zinc-600 transition ease-in-out duration-150 dark:bg-neutral-900 dark:border-neutral-700 dark:active:bg-neutral-800" aria-label="{{ __('pagination.next') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                                    <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-zinc-500 bg-white border border-zinc-200 cursor-default leading-5 dark:bg-neutral-900 dark:border-neutral-700" aria-hidden="true">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
