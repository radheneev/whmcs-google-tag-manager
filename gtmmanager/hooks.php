<?php
/**
 * Google Tag Manager Manager - Hooks
 *
 * Injects the GTM container snippets at runtime based on the module settings.
 * No theme template is ever modified.
 *
 *   ClientAreaHeadOutput   -> GTM <script> in the page <head>   (client area)
 *   ClientAreaFooterOutput -> GTM <noscript> near </body>       (client area)
 *   AdminAreaHeadOutput    -> GTM <script> in the page <head>   (admin area, optional)
 *   AdminAreaFooterOutput  -> GTM <noscript> near </body>       (admin area, optional)
 *
 * SECURITY:
 *   - The container ID is the only dynamic value placed into output.
 *   - It must match GTM-XXXXXXX exactly or nothing is injected.
 *   - No $_REQUEST / $_GET / $_POST data is ever reflected into the page.
 *
 * NOTE ON <noscript> PLACEMENT:
 *   Google's guidance is to place <noscript> immediately after <body>. WHMCS
 *   exposes no hook at that position, so we use the footer hook (fires before
 *   </body>). Per Google, footer placement of <noscript> is valid HTML and
 *   still works; only placing <noscript> inside <head> would be invalid. The
 *   main tracking happens via the <head> <script>, so this is safe.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

if (!defined('GTMMANAGER_ID_REGEX')) {
    define('GTMMANAGER_ID_REGEX', '/^GTM-[A-Z0-9]{4,20}$/');
}

/**
 * Load and cache this module's settings for the current request.
 *
 * Reads directly from tbladdonmodules. Results are cached in a static so the
 * four hooks below trigger at most one DB round-trip per page load.
 *
 * @return array{active:bool,id:string,client:bool,admin:bool}
 */
function gtmmanager_get_settings()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'active' => false,
        'id' => '',
        'client' => false,
        'admin' => false,
    ];

    try {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'gtmmanager')
            ->pluck('value', 'setting');

        // pluck() may return a Collection; normalise to a plain array.
        if (is_object($rows) && method_exists($rows, 'toArray')) {
            $rows = $rows->toArray();
        }
        $rows = (array) $rows;

        // A module is only considered active if WHMCS wrote its version row.
        $cache['active'] = array_key_exists('version', $rows);

        $rawId = isset($rows['gtm_container_id']) ? trim((string) $rows['gtm_container_id']) : '';
        // Whitelist validation. Anything not matching is discarded entirely.
        $cache['id'] = (preg_match(GTMMANAGER_ID_REGEX, $rawId) === 1) ? $rawId : '';

        $cache['client'] = (isset($rows['enable_client_area']) && $rows['enable_client_area'] === 'on');
        $cache['admin'] = (isset($rows['enable_admin_area']) && $rows['enable_admin_area'] === 'on');
    } catch (\Throwable $e) {
        // Fail closed: on any error, inject nothing. Never break page render.
        $cache = [
            'active' => false,
            'id' => '',
            'client' => false,
            'admin' => false,
        ];
    }

    return $cache;
}

/**
 * Build the GTM <script> block for the <head>.
 * $id is already validated by the caller.
 */
function gtmmanager_head_snippet($id)
{
    // $id is constrained to [A-Z0-9-] by the regex, so it is safe to embed.
    return "\n<!-- Google Tag Manager -->\n"
        . "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
        . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
        . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
        . "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
        . "})(window,document,'script','dataLayer','{$id}');</script>\n"
        . "<!-- End Google Tag Manager -->\n";
}

/**
 * Build the GTM <noscript> fallback block for near </body>.
 * $id is already validated by the caller.
 */
function gtmmanager_body_snippet($id)
{
    return "\n<!-- Google Tag Manager (noscript) -->\n"
        . "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\""
        . " height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n"
        . "<!-- End Google Tag Manager (noscript) -->\n";
}

/**
 * CLIENT AREA - head script.
 * Priority 1 so GTM loads as early as possible among head output.
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    $s = gtmmanager_get_settings();
    if (!$s['active'] || !$s['client'] || $s['id'] === '') {
        return '';
    }
    return gtmmanager_head_snippet($s['id']);
});

/**
 * CLIENT AREA - noscript fallback (near body close).
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $s = gtmmanager_get_settings();
    if (!$s['active'] || !$s['client'] || $s['id'] === '') {
        return '';
    }
    return gtmmanager_body_snippet($s['id']);
});

/**
 * ADMIN AREA - head script (optional, off by default).
 */
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    $s = gtmmanager_get_settings();
    if (!$s['active'] || !$s['admin'] || $s['id'] === '') {
        return '';
    }
    return gtmmanager_head_snippet($s['id']);
});

/**
 * ADMIN AREA - noscript fallback (optional, off by default).
 */
add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    $s = gtmmanager_get_settings();
    if (!$s['active'] || !$s['admin'] || $s['id'] === '') {
        return '';
    }
    return gtmmanager_body_snippet($s['id']);
});
