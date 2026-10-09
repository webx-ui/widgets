<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Contacts;

use WebxUi\Settings\Contacts\Contacts;

/**
 * Where the contacts widgets read their data: the "Contacts" tab of `module-settings`
 * (WIDGETS §12.1), which the package does not require. Without the module a widget prints what
 * it is handed in its props, or nothing — never an empty box.
 */
final class ContactsSource
{
    public static function get(): ?Contacts
    {
        return class_exists(Contacts::class) && app()->bound(Contacts::class) ? app(Contacts::class) : null;
    }

    /** The brand's name, the way it writes it — a word no dictionary translates. */
    public static function brand(string $kind): string
    {
        return [
            'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'viber' => 'Viber', 'signal' => 'Signal',
            'messenger' => 'Messenger', 'line' => 'LINE', 'wechat' => 'WeChat', 'discord' => 'Discord',
            'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube',
            'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'threads' => 'Threads', 'pinterest' => 'Pinterest',
            'reddit' => 'Reddit', 'twitch' => 'Twitch', 'vimeo' => 'Vimeo', 'mastodon' => 'Mastodon',
            'bluesky' => 'Bluesky', 'github' => 'GitHub', 'behance' => 'Behance', 'dribbble' => 'Dribbble',
            'medium' => 'Medium', 'tripadvisor' => 'Tripadvisor', 'vk' => 'VK',
        ][$kind] ?? ucfirst($kind);
    }

    /** The icon of a channel, or the generic one for a network the package has no picture of. */
    public static function icon(string $kind): string
    {
        return view()->exists("webx-widgets::icons.{$kind}") ? $kind : 'link';
    }
}
