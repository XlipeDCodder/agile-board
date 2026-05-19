<?php

namespace App\Services\Bot;

use App\Models\BotConfig;

class BotFactory
{
    public function fromActiveConfig(): BotProviderInterface
    {
        $config = BotConfig::active();

        if (! $config) {
            throw new BotException('Nenhuma configuração de bot ativa. Configure em Bot Config antes de usar o Icarus.');
        }

        return $this->make($config->provider, $config->api_key, $config->model);
    }

    public function make(string $provider, string $apiKey, string $model): BotProviderInterface
    {
        $verifySsl = (bool) config('services.bot.verify_ssl', true);

        return match ($provider) {
            'gemini' => new GeminiProvider($apiKey, $model, $verifySsl),
            default => throw new BotException("Provider não suportado: {$provider}"),
        };
    }
}
