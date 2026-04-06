<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Tags') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <x-button :link="route('tags.create')" class="btn-primary">{{ __('Add tag') }}</x-button>
            </div>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Slug (current locale)') }}</th>
                                <th>{{ __('EN') }}</th>
                                <th>{{ __('PL') }}</th>
                                <th class="w-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tags as $tag)
                                <tr>
                                    <td class="opacity-80">{{ $tag->category }}</td>
                                    <td class="font-medium opacity-90">{{ $tag->translations->firstWhere('locale', app()->getLocale())?->slug ?? '—' }}</td>
                                    <td class="opacity-80">{{ $tag->translations->firstWhere('locale', 'en')?->label ?? '—' }}</td>
                                    <td class="opacity-80">{{ $tag->translations->firstWhere('locale', 'pl')?->label ?? '—' }}</td>
                                    <td class="text-end">
                                        @canModifyEntity($tag)
                                            <a href="{{ route('tags.edit', $tag) }}" class="link link-primary me-3">
                                                {{ __('Edit') }}
                                            </a>

                                            <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    onclick="return confirm('{{ __('Are you sure?') }}')"
                                                    class="btn btn-ghost btn-sm text-error"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endcanModifyEntity
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center opacity-70">
                                        {{ __('No tags yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
