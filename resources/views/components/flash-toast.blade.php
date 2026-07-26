@if (session('toast'))
    <div class="toast show" id="flash-toast" data-flash-toast>{{ session('toast') }}</div>
@endif
