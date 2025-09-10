<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Starting Direct Download… | {{ config('app.name', 'Laravel') }}</title>
    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: var(--color-background);
        }
        html.dark {
            background-color: var(--color-background-dark);
        }
    </style>
    <script>
      // Apply saved theme immediately to avoid FOUC
      (function(){
        try {
          var mode = localStorage.getItem('appearance') || 'system';
          var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          var isDark = mode === 'dark' || (mode === 'system' && prefersDark);
          if (isDark) document.documentElement.classList.add('dark');
        } catch(_) {}
      })();
    </script>
    @vite('resources/css/app.css')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600" rel="stylesheet" />
  </head>
  <body class="min-h-dvh bg-background text-foreground antialiased">
    <main class="mx-auto flex min-h-dvh max-w-xl items-center justify-center p-6">
      <section class="w-full rounded-lg border bg-card p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Starting Direct Download…</h1>
        <p id="status" class="text-muted-foreground mt-1 text-sm">Preparing your link.</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <button id="copy" type="button"
                  class="inline-flex items-center justify-center whitespace-nowrap rounded-md border bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground shadow-sm transition-colors hover:bg-secondary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Copy Link
          </button>

          <a id="open" href="{{ $signedUrl }}"
             class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Open Link
          </a>
        </div>

        <p class="text-muted-foreground mt-4 text-xs">If your download doesn’t start automatically, use the buttons above.</p>
      </section>
    </main>

    <script>
      (function(){
        var openEl = document.getElementById('open');
        var url = openEl ? openEl.href : '';
        var statusEl = document.getElementById('status');
        var copyBtn = document.getElementById('copy');

        async function tryCopy() {
          try {
            await navigator.clipboard.writeText(url);
            if (statusEl) statusEl.textContent = 'Direct link copied to clipboard.';
          } catch (e) {
            if (statusEl) statusEl.textContent = 'Direct link ready.';
          }
        }

        if (copyBtn) copyBtn.addEventListener('click', tryCopy);

        // Attempt to copy immediately, then navigate after a short delay.
        tryCopy().finally(function(){
          setTimeout(function(){
            window.location.href = url;
          }, 400);
        });
      })();
    </script>
  </body>
 </html>
