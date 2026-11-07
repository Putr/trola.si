<?php

return [

    /*
    | The day the traffic filters went live (Y-m-d). History before it was
    | counted unfiltered, so the stats page marks this date.
    */
    'filters_live_since' => env('STATS_FILTERS_SINCE', '2026-11-07'),

    /*
    | Country codes counted as "Europe". Visitors from anywhere else are filtered.
    */
    'europe' => [
        'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'EU',
        'FI', 'FO', 'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT',
        'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI',
        'SK', 'SM', 'TR', 'UA', 'VA', 'XK',
    ],

    /*
    | Case-insensitive substrings of an AS description that mark a datacenter or hosting network.
    */
    'datacenter_keywords' => [
        'host', 'cloud', 'server', 'data center', 'datacenter', 'data-center', 'vps', 'colo',
        'amazon', 'google', 'microsoft', 'digitalocean', 'linode', 'ovh', 'hetzner', 'contabo',
        'scaleway', 'leaseweb', 'vultr', 'choopa', 'oracle', 'alibaba', 'tencent', 'm247',
        'datacamp', 'cdn77', 'ionos', 'netcup', 'upcloud', 'hostinger', 'godaddy', 'rackspace',
        'softlayer', 'selectel', 'timeweb', 'psychz', 'zenlayer', 'quadranet', 'frantech',
    ],

    /*
    | CDNs are not datacenters: iCloud Private Relay and WARP exit through them and carry real people.
    */
    'cdn_exclusions' => ['cloudflare', 'akamai', 'fastly'],

    /*
    | Case-insensitive substrings of a User-Agent that announce a bot, previewer, monitor or HTTP library.
    */
    'bot_patterns' => [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'whatsapp', 'telegram', 'discord',
        'preview', 'curl', 'wget', 'python', 'go-http-client', 'java/', 'okhttp', 'libwww',
        'httpclient', 'axios', 'node-fetch', 'headless', 'phantomjs', 'uptime', 'monitor',
        'pingdom', 'lighthouse', 'scrapy',
    ],

    /*
    | iptoasn.com ip2asn-combined (PDDL v1.0, public domain) and where the compiled lookup lives.
    */
    'ip_data_url' => 'https://iptoasn.com/data/ip2asn-combined.tsv.gz',

    /*
    | A download with fewer compiled ranges than this is treated as truncated and not installed
    | (production has about 207k IPv4 and 91k IPv6 ranges).
    */
    'ip_data_min_v4' => 100000,
    'ip_data_min_v6' => 20000,

    'ip_data_path' => storage_path('app/ipdata/ranges.php'),

];
