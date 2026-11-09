@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'autocomplete' => null])
@php $id = $attributes->get('id', 'f-'.$name); $error = $errors->first($name); @endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger-ink" aria-hidden="true"> *</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" class="input" value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if($required) required aria-required="true" @endif @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($hint) aria-describedby="{{ $id }}-hint" @endif
           {{ $attributes->except(['class', 'id']) }}>
    @if ($error)<p id="{{ $id }}-error" class="field-error">{{ $error }}</p>@elseif($hint)<p id="{{ $id }}-hint" class="hint">{{ $hint }}</p>@endif
</div>
