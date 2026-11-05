@props(['label', 'model', 'name' => null, 'type' => 'text', 'hint' => null, 'required' => false, 'form' => 'f'])
{{-- Alpine-bound field: <x-xfield label="Name" model="f.data.name" name="name"/> shows server validation errors for `name`. --}}
@php $id = 'x-'.($name ?? \Illuminate\Support\Str::random(5)).'-'.\Illuminate\Support\Str::random(4); @endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger-ink" aria-hidden="true"> *</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" class="input min-h-24" x-model="{{ $model }}" @if($required) required @endif {{ $attributes->except('class') }}
            @if($name) :aria-invalid="{{ $form }}.err('{{ $name }}') ? 'true' : 'false'" @endif></textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" class="input" x-model="{{ $model }}" @if($required) required @endif {{ $attributes->except('class') }}
            @if($name) :aria-invalid="{{ $form }}.err('{{ $name }}') ? 'true' : 'false'" @endif>
    @endif
    @if ($name)<p class="field-error" x-show="{{ $form }}.err('{{ $name }}')" x-text="{{ $form }}.err('{{ $name }}')" role="alert"></p>@endif
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
</div>
