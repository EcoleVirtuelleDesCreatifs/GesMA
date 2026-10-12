@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'class' => ''])
@php
    $dotted = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f_'.str_replace('.', '_', $dotted));
    $current = old($dotted, $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value));
@endphp
<div class="field {{ $class }} @error($dotted) has-error @enderror">
    @if($label)<label for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>@endif
    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id') }} @required($required)>{{ $current }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except(['id', 'options', 'placeholder']) }} @required($required)>
            @if($attributes->has('placeholder'))<option value="">{{ $attributes->get('placeholder') }}</option>@endif
            @foreach($attributes->get('options', []) as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" {{ $attributes->except('id') }} @required($required)>
    @endif
    @if($help)<div class="help">{{ $help }}</div>@endif
    @error($dotted)<div class="error">{{ $message }}</div>@enderror
</div>
