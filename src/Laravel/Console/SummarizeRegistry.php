<?php

namespace Upmind\ProvisionBase\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Upmind\ProvisionBase\Registry\Data\Contract\CategoryRegisterInterface;
use Upmind\ProvisionBase\Registry\Registry;

/**
 * Output a quick summary of the registered categories + providers.
 */
class SummarizeRegistry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upmind:provision:summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Output a quick summary of the registered provision categories and providers';

    /**
     * Execute the console command.
     */
    public function handle(Registry $registry): int
    {
        $this->outputRegistrySummary($registry);

        return 0;
    }

    /**
     * Output a summary of the registry contents.
     */
    protected function outputRegistrySummary(Registry $registry): void
    {
        $categories = $registry->getCategories();

        if ($categories->isEmpty()) {
            $this->comment('Provision registry is currently empty!');
            return;
        }

        $providers = $categories->reduce(function (Collection $providers, CategoryRegisterInterface $category) {
            return $providers->merge($category->getProviders());
        }, collect());

        $this->comment(sprintf(
            'Provision registry contains %s Categories and %s Providers.',
            $categories->count(),
            $providers->count()
        ));
    }
}
