<!-- Be present above all else. - Naval Ravikant -->
@props(['disabled' => false])
<select {{ $attributes->merge(['class' => 'w-full text-sm rounded-md border-gray-200 focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50']) }}>
    {{ $slot }}
</select>