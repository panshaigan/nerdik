@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'input input-bordered w-full rounded-md border-base-300 bg-base-100 text-base-content focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30']) }}>
