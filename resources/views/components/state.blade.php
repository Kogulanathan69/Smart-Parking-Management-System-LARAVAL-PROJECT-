@props(['kind', 'value'])
<span {{ $attributes->merge(['class' => '']) }} :class="$fmt.chip('{{ $kind }}', {{ $value }})" x-text="$fmt.stateLabel('{{ $kind }}', {{ $value }})"></span>
