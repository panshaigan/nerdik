@if ($tags->isNotEmpty())
    <div class="flex flex-wrap gap-1 {{ $class ?? 'mt-2' }}">
        @foreach ($tags as $tag)
            <span class="badge badge-primary badge-outline whitespace-normal text-left">
                {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
            </span>
        @endforeach
    </div>
@endif
