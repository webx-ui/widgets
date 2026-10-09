<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Widgets\Consent;

/**
 * `widgets.banner-on` for `widgets.banner_off`: the switch "Show the banner" of the Cookie tab
 * turned on. A setting wins over the config (W1.3), so this works for a banner switched off in
 * either place — and needs `module-settings` to write it; without it the config is the only
 * place, and the fix is not offered.
 */
final readonly class BannerOn implements AuditFix
{
    public const string ID = 'widgets.banner-on';

    /** `module-settings`, named rather than imported: the package does not require it. */
    private const string SETTINGS = 'WebxUi\Settings\Settings';

    public function id(): string
    {
        return self::ID;
    }

    public function textNamespace(): string
    {
        return 'webx-widgets';
    }

    public function fixes(): array
    {
        return [BannerOff::CHECK];
    }

    public function available(Finding $finding): bool
    {
        return class_exists(self::SETTINGS) && app()->bound(self::SETTINGS) && ! app(Consent::class)->enabled();
    }

    public function preview(Finding $finding): FixPreview
    {
        if (! $this->available($finding)) {
            return new FixPreview;
        }

        return new FixPreview([[
            'label' => (string) __('webx-widgets::panel.enabled'),
            'before' => (string) __('webx-widgets::audit.off'),
            'after' => (string) __('webx-widgets::audit.on'),
            'edit_url' => '/settings',
        ]]);
    }

    public function apply(Finding $finding): void
    {
        if ($this->available($finding)) {
            app(self::SETTINGS)->save(['consent.enabled' => true]);
        }
    }
}
