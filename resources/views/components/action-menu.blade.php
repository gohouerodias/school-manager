{{-- Reusable "⋯" row-actions dropdown. Place <form>/<button> items in the slot. --}}
<div class="row-actions-wrap" data-action-menu>
    <button type="button" class="row-actions" data-action-menu-trigger>⋯</button>
    <div class="action-menu" data-action-menu-list>
        {{ $slot }}
    </div>
</div>
