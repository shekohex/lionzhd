<?php

declare(strict_types=1);

namespace Tests\Architecture;

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel()->ignoring('App\Http\Integrations');
