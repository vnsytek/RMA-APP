@props(['name', 'bag' => 'default'])

@error($name, $bag)
    <p {{ $attributes->class(['mt-1 text-xs text-red-700']) }}>{{ $message }}</p>
@enderror
