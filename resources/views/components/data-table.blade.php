@props(['id'])

<div class="data-card">
    <div class="table-scroll">
        <table id="{{ $id }}">
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
