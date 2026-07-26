{{-- Filter select submitted as a real GET param (auto-submits on change), so
     server-side filtering and pagination stay in sync. --}}
@props(['name', 'selected' => null, 'options' => [], 'placeholder' => 'Tous'])

<select class="filter-select" name="{{ $name }}" onchange="this.form.submit()">
    <option value="">{{ $placeholder }}</option>
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected($selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
