@props(['id'])

<div class="data-card">
    <table id="{{ $id }}">
        <thead>
            <tr>{{ $head }}</tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
