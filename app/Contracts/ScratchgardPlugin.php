<?php
namespace App\Contracts;

use App\Plugins\PluginContext;

interface ScratchgardPlugin
{
    public function boot(PluginContext $plugin): void;
    public function activate(PluginContext $plugin): void;
    public function deactivate(PluginContext $plugin): void;
    public function uninstall(PluginContext $plugin, bool $purgeData = false): void;
    public function update(PluginContext $plugin, ?string $fromVersion, string $toVersion): void;
    public function health(PluginContext $plugin): array;
}
