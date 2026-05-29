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

{{-- Theme-aware pagination (DaisyUI base-*); avoids Tailwind dark: clashing with data-theme="light". --}}
<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('ui.pagination.navigation') }}" class="flex items-center justify-between">
            <div class="flex flex-1 justify-between sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="btn btn-sm btn-disabled pointer-events-none opacity-60">
                            {!! __('ui.pagination.previous') !!}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="previousPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                            class="btn btn-sm btn-outline border-base-300 bg-base-100 text-base-content"
                        >
                            {!! __('ui.pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button
                            type="button"
                            wire:click="nextPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                            class="btn btn-sm btn-outline ml-3 border-base-300 bg-base-100 text-base-content"
                        >
                            {!! __('ui.pagination.next') !!}
                        </button>
                    @else
                        <span class="btn btn-sm btn-disabled pointer-events-none ml-3 opacity-60">
                            {!! __('ui.pagination.next') !!}
                        </span>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm leading-5 text-base-content/80">
                        <span>{!! __('ui.pagination.showing') !!}</span>
                        <span class="font-medium text-base-content">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('ui.pagination.to') !!}</span>
                        <span class="font-medium text-base-content">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('ui.pagination.of') !!}</span>
                        <span class="font-medium text-base-content">{{ $paginator->total() }}</span>
                        <span>{!! __('ui.pagination.results') !!}</span>
                    </p>
                </div>

                <div>
                    <div class="join border border-base-300 bg-base-100 shadow-sm">
                        {{-- Previous --}}
                        @if ($paginator->onFirstPage())
                            <span aria-disabled="true" aria-label="{{ __('ui.pagination.previous') }}">
                                <span class="join-item btn btn-sm btn-disabled pointer-events-none opacity-50" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </span>
                        @else
                            <button
                                type="button"
                                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                                class="join-item btn btn-sm border-0 bg-base-100 text-base-content hover:bg-base-200"
                                aria-label="{{ __('ui.pagination.previous') }}"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @endif

                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="join-item btn btn-sm btn-disabled pointer-events-none border-0 border-l border-base-300 opacity-70">{{ $element }}</span>
                                </span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="join-item btn btn-sm btn-active pointer-events-none border-0 border-l border-base-300">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                                class="join-item btn btn-sm border-0 border-l border-base-300 bg-base-100 text-base-content hover:bg-base-200"
                                                aria-label="{{ __('ui.pagination.go_to_page', ['page' => $page]) }}"
                                            >
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Next --}}
                        @if ($paginator->hasMorePages())
                            <button
                                type="button"
                                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                                class="join-item btn btn-sm border-0 border-l border-base-300 bg-base-100 text-base-content hover:bg-base-200"
                                aria-label="{{ __('ui.pagination.next') }}"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @else
                            <span aria-disabled="true" aria-label="{{ __('ui.pagination.next') }}">
                                <span class="join-item btn btn-sm btn-disabled pointer-events-none border-0 border-l border-base-300 opacity-50" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </nav>
    @endif
</div>
