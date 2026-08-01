{{--
    "Guide d'utilisation" popover, next to the logo in the topbar. Links to a
    downloadable PDF guide (files/gestion-comptes_1.html sample). Drop the
    real PDF at public/guide-utilisation.pdf when it's ready.
--}}
<div class="guide-wrap" data-guide-menu>
    <button type="button" class="guide-link" data-guide-menu-trigger title="Guide d'utilisation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        Guide d'utilisation
    </button>

    <div class="guide-menu" data-guide-menu-panel>
        <p class="guide-title">Guide d'utilisation tableau de bord {{ auth()->user()?->profil?->label() ?? '' }}</p>
        <a href="{{ asset('guide-utilisation.pdf') }}" download class="guide-download">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <span>Téléchargeable</span>
        </a>
    </div>
</div>
