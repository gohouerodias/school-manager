{{-- Big, branded full-page loading overlay (see resources/js/page-loader.js).
     Included from both layouts/app.blade.php and layouts/guest.blade.php so
     every page gets the same treatment. Split in two pieces:
       - a <style> block, included in <head> *before* @vite's stylesheet, so
         it paints on the very first frame instead of waiting on a network
         request for the bundled CSS;
       - the #page-loader markup itself, included right after <body>. --}}
@once
    <style>
        #page-loader {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px;
            background: #FAF8F5;
            transition: opacity .35s ease, visibility .35s ease;
        }
        #page-loader.hide { opacity: 0; visibility: hidden; pointer-events: none; }
        .page-loader-ring { position: relative; width: 84px; height: 84px; }
        .page-loader-ring span {
            position: absolute; inset: 0; border-radius: 50%; border: 5px solid transparent;
            animation: page-loader-spin 1.15s cubic-bezier(.5, 0, .5, 1) infinite;
        }
        .page-loader-ring span:nth-child(1) { border-top-color: #3D8B75; }
        .page-loader-ring span:nth-child(2) { inset: 10px; border-top-color: #EFA03B; animation-duration: 1.5s; animation-direction: reverse; }
        .page-loader-ring span:nth-child(3) { inset: 20px; border-top-color: #26594A; animation-duration: 1.85s; }
        @keyframes page-loader-spin { to { transform: rotate(360deg); } }
        .page-loader-text {
            font-family: 'Inter', system-ui, sans-serif; font-size: 13px; font-weight: 600;
            color: #787F82; letter-spacing: .04em; text-transform: uppercase; margin: 0;
            animation: page-loader-pulse 1.4s ease-in-out infinite;
        }
        @keyframes page-loader-pulse { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
    </style>
@endonce
