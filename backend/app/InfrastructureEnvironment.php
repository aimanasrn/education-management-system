<?php

namespace App;

use RuntimeException;

class InfrastructureEnvironment
{
    public static function prepare(string $root): void
    {
        if (! is_file($root.'/compose.yaml') || ! is_file($root.'/.env.example')) {
            throw new RuntimeException('Run setup from the EMS project root.');
        }
        $path = $root.'/.env';
        $fresh = ! is_file($path);
        $text = $fresh ? file_get_contents($root.'/.env.example') : file_get_contents($path);
        $generators = [
            'APP_KEY' => fn () => 'base64:'.base64_encode(random_bytes(32)),
            'DB_PASSWORD' => fn () => bin2hex(random_bytes(24)),
            'REDIS_PASSWORD' => fn () => bin2hex(random_bytes(32)),
            'JWT_SECRET' => fn () => bin2hex(random_bytes(32)),
            'GARAGE_RPC_SECRET' => fn () => bin2hex(random_bytes(32)),
            'GARAGE_ADMIN_TOKEN' => fn () => bin2hex(random_bytes(32)),
            'GARAGE_ACCESS_KEY' => fn () => 'GK'.bin2hex(random_bytes(16)),
            'GARAGE_SECRET_KEY' => fn () => bin2hex(random_bytes(32)),
        ];
        foreach ($generators as $name => $generate) {
            $pattern = '/^'.preg_quote($name, '/').'=(.*)$/m';
            $found = preg_match($pattern, $text, $match);
            $value = $found ? trim($match[1], " \t\r\n\"'") : '';
            if ($value !== '' && ! ($fresh && $name === 'DB_PASSWORD')) {
                continue;
            }
            $line = $name.'='.$generate();
            $text = $found ? preg_replace($pattern, $line, $text) : rtrim($text)."\n".$line."\n";
        }
        $temporary = tempnam($root, '.env.infrastructure-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to prepare the local environment file.');
        }
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $text, LOCK_EX) !== strlen($text) || ! rename($temporary, $path)) {
                throw new RuntimeException('Unable to write the local environment file.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
