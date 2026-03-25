<?php

declare(strict_types=1);

namespace {
    use App\Models\User;

    function browserLogin(User $user): object
    {
        $page = visit(route('login'))
            ->waitForText('Log in to your account')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Log in')
            ->assertNoJavaScriptErrors();

        expect(browserWaitForPath($page, '/login'))->toBeTrue();

        $page->fill('Email address', $user->email)
            ->fill('Password', 'password')
            ->press('Log in')
            ->assertNoJavaScriptErrors();

        expect(browserWaitForPath($page, '/discover'))->toBeTrue();

        return $page->waitForText('Discover')
            ->assertNoJavaScriptErrors();
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
}
