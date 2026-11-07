@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'autofocus' => false,
    'required' => true,
])

<div class="sign-in-field">
    <label for="{{ $name }}">{{ $label }}</label>
    @if ($type === 'password')
        <div class="password-wrap">
            <input id="{{ $name }}" name="{{ $name }}" type="password" maxlength="255"
                   class="form-control @error($name) is-invalid @enderror"
                   @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                   @required($required) @if ($autofocus) autofocus @endif>
            <button type="button" class="password-toggle" data-toggle-password="{{ $name }}">Show</button>
        </div>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" maxlength="255"
               value="{{ old($name, $value) }}"
               class="form-control @error($name) is-invalid @enderror"
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @required($required) @if ($autofocus) autofocus @endif>
    @endif
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
