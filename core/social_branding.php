<?php

if (!function_exists('qrk_social_logo_svg_data_uri')) {
    function qrk_social_logo_svg_data_uri(string $svg): string
    {
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}

if (!function_exists('qrk_social_logo_cdn_url')) {
    function qrk_social_logo_cdn_url(string $slug, ?string $hex_color = null): string
    {
        $normalized_slug = strtolower(trim($slug));
        if ($normalized_slug === '') {
            return '';
        }

        $url = 'https://cdn.simpleicons.org/' . rawurlencode($normalized_slug);
        $normalized_color = strtoupper(ltrim((string) $hex_color, '#'));
        if ($normalized_color !== '' && preg_match('/^[0-9A-F]{6}$/', $normalized_color)) {
            $url .= '/' . $normalized_color;
        }

        return $url . '?viewbox=auto';
    }
}

if (!function_exists('qrk_social_logo_map')) {
    function qrk_social_logo_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [
            'instagram' => [
                'label' => 'Instagram',
                'logo' => qrk_social_logo_cdn_url('instagram'),
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'logo' => qrk_social_logo_cdn_url('linkedin'),
            ],
            'whatsapp' => [
                'label' => 'WhatsApp',
                'logo' => qrk_social_logo_cdn_url('whatsapp'),
            ],
            'x' => [
                'label' => 'X / Twitter',
                'logo' => qrk_social_logo_cdn_url('x'),
            ],
            'twitter' => [
                'label' => 'X / Twitter',
                'logo' => qrk_social_logo_cdn_url('x'),
            ],
            'telegram' => [
                'label' => 'Telegram',
                'logo' => qrk_social_logo_cdn_url('telegram'),
            ],
            'youtube' => [
                'label' => 'YouTube',
                'logo' => qrk_social_logo_cdn_url('youtube'),
            ],
            'facebook' => [
                'label' => 'Facebook',
                'logo' => qrk_social_logo_cdn_url('facebook'),
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'logo' => qrk_social_logo_cdn_url('tiktok'),
            ],
            'website' => [
                'label' => 'Web Sitesi',
                'logo' => qrk_social_logo_svg_data_uri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect x="10" y="12" width="44" height="40" rx="10" fill="#2563EB"/><rect x="14" y="18" width="36" height="28" rx="6" fill="#DBEAFE"/><path d="M14 24h36" stroke="#2563EB" stroke-width="2.8"/><circle cx="19" cy="21" r="1.8" fill="#93C5FD"/><circle cx="24" cy="21" r="1.8" fill="#93C5FD"/><circle cx="29" cy="21" r="1.8" fill="#93C5FD"/><path d="M22 33h20M22 39h12" stroke="#2563EB" stroke-width="3.4" stroke-linecap="round"/><path d="M40 31l6 5-6 5z" fill="#2563EB"/></svg>'),
            ],
            'mail' => [
                'label' => 'E-posta',
                'logo' => qrk_social_logo_svg_data_uri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect x="10" y="16" width="44" height="32" rx="8" fill="#2563EB"/><path d="M16 24l16 12 16-12" fill="none" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 40V26m32 14V26" fill="none" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/></svg>'),
            ],
            'phone' => [
                'label' => 'Telefon',
                'logo' => qrk_social_logo_svg_data_uri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M23.8 14.5c1.7-1.1 3.8-.8 4.9.9l4.1 6c1 1.5.9 3.5-.4 4.8l-2.6 2.6c2.4 4.7 6.1 8.4 10.8 10.8l2.6-2.6c1.3-1.3 3.4-1.4 4.8-.4l6 4.1c1.7 1.1 2 3.2.9 4.9l-2.4 3.7c-1.1 1.7-3.3 2.6-5.4 2.1C31.1 48 16 32.9 12.5 18.8c-.5-2.1.3-4.3 2.1-5.4z" fill="#16A34A"/></svg>'),
            ],
            'maps' => [
                'label' => 'Harita',
                'logo' => qrk_social_logo_svg_data_uri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M12 18l12-4 16 4 12-4v30l-12 4-16-4-12 4z" fill="#E5E7EB"/><path d="M24 14v30m16-26v30" stroke="#94A3B8" stroke-width="2.4"/><path d="M32 18c-6.1 0-11 4.9-11 11 0 8.3 11 19 11 19s11-10.7 11-19c0-6.1-4.9-11-11-11zm0 14.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z" fill="#EF4444"/></svg>'),
            ],
            '__custom__' => [
                'label' => 'Diğer',
                'logo' => qrk_social_logo_svg_data_uri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M26 38l-4 4a6 6 0 1 1-8.5-8.5l7-7a6 6 0 0 1 8.5 0" fill="none" stroke="#475569" stroke-width="4" stroke-linecap="round"/><path d="M38 26l4-4a6 6 0 1 1 8.5 8.5l-7 7a6 6 0 0 1-8.5 0" fill="none" stroke="#475569" stroke-width="4" stroke-linecap="round"/><path d="M25 39l14-14" fill="none" stroke="#475569" stroke-width="4" stroke-linecap="round"/></svg>'),
            ],
        ];

        return $map;
    }
}

if (!function_exists('qrk_get_social_platform_meta')) {
    function qrk_get_social_platform_meta(?string $platform): array
    {
        $normalized = strtolower(trim((string) $platform));
        $aliases = [
            'x-twitter' => 'x',
            'twitter' => 'x',
        ];
        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        $map = qrk_social_logo_map();
        return $map[$normalized] ?? $map['__custom__'];
    }
}

if (!function_exists('qrk_get_social_platform_options')) {
    function qrk_get_social_platform_options(): array
    {
        $order = ['instagram', 'linkedin', 'whatsapp', 'x', 'telegram', 'youtube', 'facebook', 'tiktok', 'website', 'mail', 'phone', 'maps', '__custom__'];
        $map = qrk_social_logo_map();
        $options = [];

        foreach ($order as $key) {
            $meta = $map[$key] ?? $map['__custom__'];
            $options[] = [
                'value' => $key,
                'label' => $meta['label'],
                'logo' => $meta['logo'],
            ];
        }

        return $options;
    }
}
