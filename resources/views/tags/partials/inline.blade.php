@if ($tags->isNotEmpty())
    <div class="flex flex-wrap gap-1 {{ $class ?? 'mt-2' }}">
        @foreach ($tags as $tag)
            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-800 ring-1 ring-indigo-100">
                {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
            </span>
        @endforeach
    </div>
@endif
