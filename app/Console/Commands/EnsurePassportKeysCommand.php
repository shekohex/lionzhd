<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

final class EnsurePassportKeysCommand extends Command
{
    protected $signature = 'passport:keys:ensure {--length=4096 : The length of the private key}';

    protected $description = 'Generate Passport encryption keys when none are configured or stored';

    public function handle(): int
    {
        $configuredPrivateKey = config('passport.private_key');
        $configuredPublicKey = config('passport.public_key');

        if (filled($configuredPrivateKey) || filled($configuredPublicKey)) {
            if (! filled($configuredPrivateKey) || ! filled($configuredPublicKey)) {
                $this->components->error('Both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY must be configured together.');

                return self::FAILURE;
            }

            $this->components->info('Passport encryption keys are configured through the environment.');

            return self::SUCCESS;
        }

        $keyPath = (string) config('passport.key_path', storage_path());

        if (! is_dir($keyPath) && ! mkdir($keyPath, 0700, true) && ! is_dir($keyPath)) {
            $this->components->error("Unable to create the Passport key directory: {$keyPath}");

            return self::FAILURE;
        }

        Passport::loadKeysFrom($keyPath);

        $lock = fopen($keyPath.'/.passport-keys.lock', 'c');

        if ($lock === false) {
            $this->components->error("Unable to open the Passport key lock: {$keyPath}/.passport-keys.lock");

            return self::FAILURE;
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                $this->components->error('Unable to acquire the Passport key generation lock.');

                return self::FAILURE;
            }

            $privateKeyPath = Passport::keyPath('oauth-private.key');
            $publicKeyPath = Passport::keyPath('oauth-public.key');
            $privateKeyExists = file_exists($privateKeyPath);
            $publicKeyExists = file_exists($publicKeyPath);

            if ($privateKeyExists && $publicKeyExists) {
                $this->components->info('Passport encryption keys already exist.');

                return self::SUCCESS;
            }

            if ($privateKeyExists || $publicKeyExists) {
                $this->components->error('Only one Passport encryption key exists. Restore the missing key or remove the remaining key before restarting.');

                return self::FAILURE;
            }

            return $this->call('passport:keys', [
                '--length' => (string) $this->option('length'),
                '--no-interaction' => true,
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
