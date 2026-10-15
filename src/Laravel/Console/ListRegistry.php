<?php

namespace Upmind\ProvisionBase\Laravel\Console;

use Illuminate\Console\Command;
use Upmind\ProvisionBase\Registry\Data\Contract\ProviderRegisterInterface;
use Upmind\ProvisionBase\Registry\Registry;

/**
 * Output a table of all currently registered provision categories and providers.
 */
class ListRegistry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upmind:provision:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Output a table of registered provision categories and providers';

    /**
     * Execute the console command.
     */
    public function handle(Registry $registry): int
    {
        $this->call('upmind:provision:summary');

        $this->outputRegistryTable($registry);

        return 0;
    }

    protected function outputRegistryTable(Registry $registry): void
    {
        $headers = ['Category', 'Providers'];
        $rows = [];

        $categories = $registry->getCategories();

        if ($categories->isEmpty()) {
            return;
        }

        foreach ($categories as $category) {
            $categoryIdentifier = $category->getIdentifier();
            $providerIdentifiers = $category->getProviders()
                ->map(function (ProviderRegisterInterface $provider) {
                    return $provider->getIdentifier();
                });


            $rows[] = [$categoryIdentifier, $providerIdentifiers->implode("\n")];
        }

        $this->table($headers, $rows);
    }
}
