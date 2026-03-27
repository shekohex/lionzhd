<?php

declare(strict_types=1);

namespace {
    use App\Models\User;

    function browserLogin(User $user): object
    {
        $page = visit(route('login'));

        $resolvedPath = browserWaitForPaths($page, ['/login', '/discover']);

        if ($resolvedPath === '/discover') {
            return $page
                ->waitForText('Discover')
                ->assertNoJavaScriptErrors();
        }

        if ($resolvedPath === '/login') {
            $page = $page
                ->waitForText('Log in to your account')
                ->assertSee('Email address')
                ->assertSee('Password')
                ->assertSee('Log in')
                ->assertNoJavaScriptErrors();

            expect(browserWaitForPath($page, '/login'))->toBeTrue();

            $page->fill('Email address', $user->email)
                ->fill('Password', 'password')
                ->waitForText('Log in');

            $page->script(<<<'JS'
                () => {
                    const form = Array.from(document.querySelectorAll('form')).find((candidate) => {
                        const action = candidate.getAttribute('action') ?? '';

                        return action.includes('/login') || action === '' || action.endsWith('/login');
                    });

                    if (!form) {
                        return false;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();

                        return true;
                    }

                    form.submit();

                    return true;
                }
            JS);

            $page->assertNoJavaScriptErrors();

            expect(browserWaitForPath($page, '/discover'))->toBeTrue();

            return $page->waitForText('Discover')
                ->assertNoJavaScriptErrors();
        }

        expect($resolvedPath)->toBe('/login');

        return $page;
    }

    function browserLoginAndVisit(User $user, string $url): object
    {
        $page = browserLogin($user);

        $urlJson = json_encode($url, JSON_THROW_ON_ERROR);

        $page->script(str_replace('__URL__', $urlJson, <<<'JS'
            () => {
                window.location.assign(__URL__);

                return true;
            }
        JS));

        return $page;
    }

    function browserWaitForPath(object $page, string $path): bool
    {
        $pathJson = json_encode($path, JSON_THROW_ON_ERROR);

        return $page->script(str_replace('__PATH__', $pathJson, <<<'JS'
            async () => {
                const path = __PATH__;
                const startedAt = Date.now();

                while (Date.now() - startedAt < 3000) {
                    if (window.location.pathname === path) {
                        return true;
                    }

                    await new Promise((resolve) => window.setTimeout(resolve, 50));
                }

                return false;
            }
        JS));
    }

function browserWaitForPaths(object $page, array $paths): ?string
{
    $pathsJson = json_encode($paths, JSON_THROW_ON_ERROR);

    return $page->script(str_replace('__PATHS__', $pathsJson, <<<'JS'
        async () => {
            const normalizePath = (path) => {
                if (path.length > 1 && path.endsWith('/')) {
                    return path.slice(0, -1);
                }

                return path;
            };

            const paths = new Set((__PATHS__).map(normalizePath));
            const startedAt = Date.now();

            while (Date.now() - startedAt < 3000) {
                const currentPath = window.location.pathname;
                const normalizedPath = normalizePath(currentPath);

                if (paths.has(normalizedPath)) {
                    return currentPath;
                }

                    await new Promise((resolve) => window.setTimeout(resolve, 50));
                }

                return null;
            }
        JS));
    }
}
