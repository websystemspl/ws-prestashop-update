<?php

namespace Websystems\PrestashopUpdatePackage\License;

use Websystems\PrestashopUpdatePackage\Translations;
use Websystems\PrestashopUpdatePackage\Update\UpdatePulseClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Renders the "License & Updates" admin tab as an HTML string.
 *
 * Usage:
 *   $renderer = new LicenseTabRenderer($client, Translations::getLocale(), $module->name);
 *   $html = $renderer->render($adminUrl, $errors, $confirmations);
 *   $this->context->smarty->assign('ws_license_tab_html', $html);
 */
class LicenseTabRenderer
{
    /** @var UpdatePulseClient */
    private $client;

    /** @var string */
    private $locale;

    /** @var string */
    private $moduleName;

    /**
     * @param UpdatePulseClient $client     Initialised update/license client
     * @param string            $locale     Language iso_code, e.g. 'pl' or 'en'
     * @param string            $moduleName Module name used to namespace HTML element IDs
     */
    public function __construct(UpdatePulseClient $client, string $locale, string $moduleName)
    {
        $this->client     = $client;
        $this->locale     = $locale;
        $this->moduleName = $moduleName;
    }

    /**
     * Renders the full license & update tab HTML.
     *
     * @param string   $adminUrl       URL for form actions (POST target)
     * @param string[] $errors         Error messages to display at top
     * @param string[] $confirmations  Success messages to display at top
     *
     * @return string HTML string ready to inject into a Smarty template
     */
    public function render(string $adminUrl, array $errors = [], array $confirmations = []): string
    {
        $submitBase = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');
        $moduleId   = htmlspecialchars($this->moduleName, ENT_QUOTES, 'UTF-8');

        // Fetch update data (uses cache, no forced refresh)
        $updateInfo      = $this->client->checkForUpdates(false);
        $currentVersion  = htmlspecialchars($this->client->getCurrentVersion(), ENT_QUOTES, 'UTF-8');
        $latestVersion   = $updateInfo['version'] ?? null;
        $updateAvailable = $latestVersion
            && version_compare($latestVersion, $this->client->getCurrentVersion(), '>');
        $downloadUrl     = ($updateAvailable && !empty($updateInfo['download_url']))
            ? $updateInfo['download_url']
            : null;
        $licenseStatus = $this->client->getLicenseStatus();

        $html = '';

        // ── Alert messages ────────────────────────────────────────────────────
        foreach ($confirmations as $msg) {
            $html .= '<div class="alert alert-success">'
                . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                . '</div>' . "\n";
        }
        foreach ($errors as $msg) {
            $html .= '<div class="alert alert-danger">'
                . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                . '</div>' . "\n";
        }

        // ── UPDATE SECTION ────────────────────────────────────────────────────
        $html .= '<div class="panel panel-default" style="margin-bottom:20px">' . "\n";
        $html .= '  <div class="panel-heading">'
            . '<i class="icon-refresh"></i> '
            . $this->h('Module updates')
            . '</div>' . "\n";
        $html .= '  <div class="panel-body">' . "\n";

        // Installed version row
        $html .= '    <div class="row" style="margin-bottom:12px">' . "\n";
        $html .= '      <div class="col-lg-3 control-label" style="font-weight:600;padding-top:7px">'
            . $this->h('Installed version') . '</div>' . "\n";
        $html .= '      <div class="col-lg-9" style="padding-top:7px">'
            . '<span class="label label-info">' . $currentVersion . '</span></div>' . "\n";
        $html .= '    </div>' . "\n";

        // Latest version row (only when server responded)
        if ($latestVersion !== null) {
            $latestSafe = htmlspecialchars($latestVersion, ENT_QUOTES, 'UTF-8');
            $html .= '    <div class="row" style="margin-bottom:12px">' . "\n";
            $html .= '      <div class="col-lg-3 control-label" style="font-weight:600;padding-top:7px">'
                . $this->h('Latest available version') . '</div>' . "\n";
            $html .= '      <div class="col-lg-9" style="padding-top:7px">' . "\n";

            if ($updateAvailable) {
                $html .= '        <span class="label label-danger">' . $latestSafe . '</span>'
                    . '&nbsp;<strong style="color:#e74c3c">' . $this->h('Update available!') . '</strong>' . "\n";

                if ($downloadUrl) {
                    $confirmInstall = addslashes(
                        Translations::trans(
                            'This will replace module files automatically. Make sure you have a backup. Continue?',
                            $this->locale
                        )
                    );
                    $html .= '        &nbsp;'
                        . '<form method="post" action="' . $submitBase . '" style="display:inline">' . "\n"
                        . '          <input type="hidden" name="submitWsInstallUpdate" value="1" />' . "\n"
                        . '          <button type="submit" class="btn btn-sm btn-success"'
                        . ' onclick="return confirm(\'' . htmlspecialchars($confirmInstall, ENT_QUOTES, 'UTF-8') . '\')">'
                        . '<i class="icon-download"></i> ' . $this->h('Install update')
                        . '</button>' . "\n"
                        . '        </form>' . "\n";
                    $html .= '        &nbsp;'
                        . '<a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '"'
                        . ' class="btn btn-sm btn-default" target="_blank">'
                        . '<i class="icon-download"></i> ' . $this->h('Download ZIP')
                        . '</a>' . "\n";
                }
            } else {
                $html .= '        <span class="label label-success">' . $latestSafe . '</span>'
                    . '&nbsp;<span style="color:#27ae60">' . $this->h('Up to date') . '</span>' . "\n";
            }

            $html .= '      </div>' . "\n";
            $html .= '    </div>' . "\n";
        }

        // Check for updates button
        $html .= '    <div class="row">' . "\n"
            . '      <div class="col-lg-9 col-lg-offset-3">' . "\n"
            . '        <form method="post" action="' . $submitBase . '">' . "\n"
            . '          <input type="hidden" name="submitWsCheckUpdate" value="1" />' . "\n"
            . '          <button type="submit" class="btn btn-default">'
            . '<i class="icon-refresh"></i> ' . $this->h('Check for updates now')
            . '</button>' . "\n"
            . '        </form>' . "\n"
            . '      </div>' . "\n"
            . '    </div>' . "\n";

        $html .= '  </div>' . "\n"; // .panel-body
        $html .= '</div>' . "\n";   // .panel

        // ── LICENSE SECTION ───────────────────────────────────────────────────
        $html .= '<div class="panel panel-default">' . "\n";
        $html .= '  <div class="panel-heading">'
            . '<i class="icon-key"></i> ' . $this->h('License')
            . '</div>' . "\n";
        $html .= '  <div class="panel-body">' . "\n";

        // Registered domain row
        $html .= '    <div class="row" style="margin-bottom:12px">' . "\n";
        $html .= '      <div class="col-lg-3 control-label" style="font-weight:600;padding-top:7px">'
            . $this->h('Registered domain') . '</div>' . "\n";
        $html .= '      <div class="col-lg-9" style="padding-top:7px">'
            . '<code>' . htmlspecialchars($licenseStatus['domain'], ENT_QUOTES, 'UTF-8') . '</code>'
            . '</div>' . "\n";
        $html .= '    </div>' . "\n";

        // License status row
        $html .= '    <div class="row" style="margin-bottom:20px">' . "\n";
        $html .= '      <div class="col-lg-3 control-label" style="font-weight:600;padding-top:7px">'
            . $this->h('License status') . '</div>' . "\n";
        $html .= '      <div class="col-lg-9" style="padding-top:7px">' . "\n";

        if ($licenseStatus['is_activated']) {
            $html .= '        <span class="label label-success">'
                . '<i class="icon-check"></i> ' . $this->h('Active')
                . '</span>'
                . '&nbsp;<code>' . htmlspecialchars($licenseStatus['license_key_masked'], ENT_QUOTES, 'UTF-8') . '</code>' . "\n";
        } elseif ($licenseStatus['has_license']) {
            $html .= '        <span class="label label-warning">'
                . '<i class="icon-warning-sign"></i> ' . $this->h('Key saved, not activated')
                . '</span>' . "\n";
        } else {
            $html .= '        <span class="label label-danger">'
                . '<i class="icon-times"></i> ' . $this->h('No license')
                . '</span>' . "\n";
        }

        $html .= '      </div>' . "\n";
        $html .= '    </div>' . "\n";

        if ($licenseStatus['is_activated']) {
            // ── Deactivate form
            $confirmDeactivate = addslashes(
                Translations::trans(
                    'Are you sure you want to deactivate the license on this domain?',
                    $this->locale
                )
            );
            $html .= '    <form method="post" action="' . $submitBase . '">' . "\n"
                . '      <input type="hidden" name="submitWsLicenseDeactivate" value="1" />' . "\n"
                . '      <div class="row"><div class="col-lg-9 col-lg-offset-3">' . "\n"
                . '        <button type="submit" class="btn btn-default"'
                . ' onclick="return confirm(\'' . htmlspecialchars($confirmDeactivate, ENT_QUOTES, 'UTF-8') . '\')">'
                . '<i class="icon-sign-out"></i> ' . $this->h('Deactivate license')
                . '</button>' . "\n"
                . '      </div></div>' . "\n"
                . '    </form>' . "\n";
        } else {
            // ── Activate form
            $inputId = 'ws_license_key_' . $moduleId;
            $html .= '    <form method="post" action="' . $submitBase . '">' . "\n"
                . '      <input type="hidden" name="submitWsLicenseActivate" value="1" />' . "\n"
                . '      <div class="form-group row">' . "\n"
                . '        <label class="col-lg-3 control-label" for="' . $inputId . '">'
                . $this->h('License key') . '</label>' . "\n"
                . '        <div class="col-lg-6">' . "\n"
                . '          <input type="text" name="ws_license_key" id="' . $inputId . '"'
                . ' class="form-control" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"'
                . ' autocomplete="off" required />' . "\n"
                . '        </div>' . "\n"
                . '      </div>' . "\n"
                . '      <div class="form-group row">' . "\n"
                . '        <div class="col-lg-9 col-lg-offset-3">' . "\n"
                . '          <button type="submit" class="btn btn-primary">'
                . '<i class="icon-key"></i> ' . $this->h('Activate license')
                . '</button>' . "\n"
                . '        </div>' . "\n"
                . '      </div>' . "\n"
                . '    </form>' . "\n";
        }

        $html .= '  </div>' . "\n"; // .panel-body
        $html .= '</div>' . "\n";   // .panel

        return $html;
    }

    /**
     * Translates and HTML-escapes a string.
     *
     * @param string   $key    Translation key
     * @param string[] $params Optional sprintf parameters
     */
    private function h(string $key, array $params = []): string
    {
        return htmlspecialchars(
            Translations::trans($key, $this->locale, $params),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
