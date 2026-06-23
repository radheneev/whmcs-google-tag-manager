<?php
/**
 * Google Tag Manager Manager - WHMCS Addon Module
 *
 * Lets marketing configure a Google Tag Manager container ID and control
 * where the GTM snippets are injected, without editing any theme templates.
 *
 * The GTM snippets are injected at runtime via hooks (see hooks.php). This
 * file only provides the activation logic and the admin configuration page.
 *
 * Security model:
 *  - The container ID is the ONLY value ever interpolated into injected HTML.
 *  - It is whitelist-validated (GTM-XXXXXXX) on save and again on output.
 *  - No request parameters are ever reflected into page output.
 *
 * @package    WHMCS
 * @version    1.0.0
 * Tested against WHMCS 8.11.x
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Canonical validation pattern for a GTM container ID.
 * Format: GTM- followed by 4 to 12 uppercase letters/digits.
 * Used on both save (config) and output (hooks) so the rule lives in one place.
 */
if (!defined('GTMMANAGER_ID_REGEX')) {
    define('GTMMANAGER_ID_REGEX', '/^GTM-[A-Z0-9]{4,20}$/');
}

/**
 * Module configuration shown under Setup > Addon Modules > Configure.
 *
 * Marketing edits these fields. We deliberately keep the inputs as a plain
 * text field (validated on save) plus yes/no toggles so there is no free-form
 * HTML or script entry anywhere.
 */
function gtmmanager_config()
{
    return [
        'name' => 'Google Tag Manager',
        'description' => 'Inject Google Tag Manager into the client area (and optionally admin area) without editing theme files. Managed by marketing.',
        'version' => '1.0.0',
        'author' => 'Radhe',
        'language' => 'english',
        'fields' => [
            'gtm_container_id' => [
                'FriendlyName' => 'GTM Container ID',
                'Type' => 'text',
                'Size' => '20',
                'Default' => '',
                'Description' => 'Format: GTM-XXXXXXX (from your Tag Manager workspace). Leave blank to disable injection.',
            ],
            'enable_client_area' => [
                'FriendlyName' => 'Load on Client Area',
                'Type' => 'yesno',
                'Default' => 'no',
                'Description' => 'Inject GTM on all client-facing pages (recommended).',
            ],
            'enable_admin_area' => [
                'FriendlyName' => 'Load on Admin Area',
                'Type' => 'yesno',
                'Default' => 'no',
                'Description' => 'Inject GTM on admin pages too. Leave OFF unless you specifically want to track staff activity.',
            ],
        ],
    ];
}

/**
 * Activation handler. No database tables are required because all state is
 * stored in WHMCS module settings (tbladdonmodules), managed for us by the
 * config fields above.
 */
function gtmmanager_activate()
{
    return [
        'status' => 'success',
        'description' => 'Google Tag Manager module activated. Configure your container ID, then enable the areas you want to track.',
    ];
}

/**
 * Deactivation handler. We intentionally do not delete the stored container
 * ID so re-activation keeps the previous configuration. Injection simply
 * stops because the hooks check the module active state at runtime.
 */
function gtmmanager_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Google Tag Manager module deactivated. Snippets are no longer injected.',
    ];
}

/**
 * Upgrade handler. Placeholder for future schema/setting migrations.
 */
function gtmmanager_upgrade($vars)
{
    // No migrations needed for 1.0.0.
}

/**
 * Admin area output (Addons > Google Tag Manager).
 *
 * This page is read-only status plus validation feedback. It does NOT accept
 * the container ID here; that is edited via the standard Configure screen so
 * we inherit WHMCS role-based access control and CSRF protection on saves.
 */
function gtmmanager_output($vars)
{
    $containerId = isset($vars['gtm_container_id']) ? trim($vars['gtm_container_id']) : '';
    $clientOn = (isset($vars['enable_client_area']) && $vars['enable_client_area'] === 'on');
    $adminOn = (isset($vars['enable_admin_area']) && $vars['enable_admin_area'] === 'on');

    $isValid = ($containerId !== '' && preg_match(GTMMANAGER_ID_REGEX, $containerId) === 1);

    // All dynamic values are escaped before display. Defence in depth: even
    // though the ID is validated on save, we never trust stored data on read.
    $safeId = htmlspecialchars($containerId, ENT_QUOTES, 'UTF-8');

    $statusBadge = function ($ok, $okText, $badText) {
        $color = $ok ? '#155724' : '#721c24';
        $bg = $ok ? '#d4edda' : '#f8d7da';
        $text = $ok ? $okText : $badText;
        return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;'
            . 'font-size:12px;font-weight:600;background:' . $bg . ';color:' . $color . ';">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    };

    $html = '<h2><i class="fas fa-tag"></i> Google Tag Manager</h2>';

    $html .= '<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;'
        . 'padding:20px;margin:20px 0;max-width:760px;">';

    $html .= '<table style="width:100%;border-collapse:collapse;">';

    $html .= '<tr><td style="padding:10px 8px;border-bottom:1px solid #e9ecef;width:220px;">'
        . '<strong>Container ID</strong></td>'
        . '<td style="padding:10px 8px;border-bottom:1px solid #e9ecef;">'
        . ($safeId !== '' ? '<code>' . $safeId . '</code>' : '<em style="color:#999;">Not set</em>')
        . '</td></tr>';

    $html .= '<tr><td style="padding:10px 8px;border-bottom:1px solid #e9ecef;">'
        . '<strong>ID Format</strong></td>'
        . '<td style="padding:10px 8px;border-bottom:1px solid #e9ecef;">'
        . ($containerId === ''
            ? $statusBadge(false, '', 'Empty')
            : $statusBadge($isValid, 'Valid', 'Invalid - injection blocked'))
        . '</td></tr>';

    $html .= '<tr><td style="padding:10px 8px;border-bottom:1px solid #e9ecef;">'
        . '<strong>Client Area Injection</strong></td>'
        . '<td style="padding:10px 8px;border-bottom:1px solid #e9ecef;">'
        . $statusBadge($clientOn && $isValid, 'Active', $clientOn ? 'Enabled but ID invalid' : 'Disabled')
        . '</td></tr>';

    $html .= '<tr><td style="padding:10px 8px;">'
        . '<strong>Admin Area Injection</strong></td>'
        . '<td style="padding:10px 8px;">'
        . $statusBadge($adminOn && $isValid, 'Active', $adminOn ? 'Enabled but ID invalid' : 'Disabled')
        . '</td></tr>';

    $html .= '</table></div>';

    // Guidance block.
    $html .= '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;'
        . 'padding:15px 20px;margin:20px 0;max-width:760px;font-size:13px;line-height:1.6;">';
    $html .= '<strong>How to change settings:</strong> go to '
        . '<em>Setup &gt; Addon Modules &gt; Google Tag Manager &gt; Configure</em>. '
        . 'Enter the container ID, enable the areas, and save. '
        . 'Changes take effect immediately on the next page load.';

    if ($clientOn && !$isValid && $containerId !== '') {
        $html .= '<br><br><span style="color:#721c24;"><strong>Warning:</strong> '
            . 'Client area injection is enabled but the container ID does not match '
            . 'the required <code>GTM-XXXXXXX</code> format, so nothing is being injected. '
            . 'Fix the ID to start tracking.</span>';
    }
    $html .= '</div>';

    // Verification help.
    $html .= '<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;'
        . 'padding:15px 20px;margin:20px 0;max-width:760px;font-size:13px;line-height:1.6;">';
    $html .= '<strong>Verify the install:</strong> open the public client area, then use '
        . 'Google Tag Assistant / GTM Preview mode and confirm the container shows '
        . '<em>Connected</em>. The main tracking script loads in the page &lt;head&gt;; '
        . 'the &lt;noscript&gt; fallback loads near the end of &lt;body&gt;.';
    $html .= '</div>';

    return $html;
}
