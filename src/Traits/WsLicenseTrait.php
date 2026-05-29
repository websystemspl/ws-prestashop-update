<?php

namespace Websystems\PrestashopUpdatePackage\Traits;

use Websystems\PrestashopUpdatePackage\License\LicenseTabRenderer;
use Websystems\PrestashopUpdatePackage\Translations;
use Websystems\PrestashopUpdatePackage\Update\UpdatePulseClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Drop-in trait that adds license activation / deactivation and module update
 * management to any PrestaShop admin controller (or module admin class).
 *
 * Minimal integration example inside an AdminController:
 *
 *   use Websystems\PrestashopUpdatePackage\Traits\WsLicenseTrait;
 *
 *   class AdminMyModuleController extends ModuleAdminController
 *   {
 *       use WsLicenseTrait;
 *
 *       public function __construct()
 *       {
 *           parent::__construct();
 *           $this->wsInitUpdateManager($this->module, 'WS_MYMODULE');
 *       }
 *
 *       public function postProcess()
 *       {
 *           $this->wsHandleLicenseActions();
 *           parent::postProcess();
 *       }
 *
 *       public function renderView()
 *       {
 *           // Show license tab when $ws_tab === 'license'
 *           if (Tools::getValue('ws_tab') === 'license') {
 *               $adminUrl = $this->context->link->getAdminLink('AdminMyModule') . '&ws_tab=license';
 *               return $this->wsRenderLicenseTab($adminUrl);
 *           }
 *           return parent::renderView();
 *       }
 *   }
 */
trait WsLicenseTrait
{
    /** @var UpdatePulseClient|null */
    private $wsUpdateClient = null;

    /** @var string[] */
    private $wsLicenseErrors = [];

    /** @var string[] */
    private $wsLicenseConfirmations = [];

    /** @var string iso_code locale, e.g. 'pl' or 'en' */
    private $wsLocale = 'en';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Initialises the UpdatePulse client for the given module and config prefix.
     * Safe to call inside a controller constructor; errors are silently caught.
     *
     * @param \Module $module        The PrestaShop module instance
     * @param string  $configPrefix  Prefix for Configuration keys, e.g. 'WS_MYMODULE'
     */
    public function wsInitUpdateManager(\Module $module, string $configPrefix): void
    {
        try {
            $this->wsUpdateClient = new UpdatePulseClient($module, $configPrefix);
            $this->wsLocale       = Translations::getLocale();
        } catch (\Exception $e) {
            $this->wsUpdateClient = null;
        }
    }

    /**
     * Silently checks for updates using the 24-hour cache.
     * Contacts the server only when the cache is stale; otherwise returns immediately.
     * Does not render anything — safe to call from hookDisplayBackOfficeHeader.
     */
    public function wsCheckForUpdates(): void
    {
        if ($this->wsUpdateClient === null) {
            return;
        }

        $this->wsUpdateClient->checkForUpdates(false);
    }

    /**
     * Dispatches submit actions from the license/update forms.
     * Call this in postProcess() (or equivalent) before rendering.
     */
    public function wsHandleLicenseActions(): void
    {
        if (\Tools::isSubmit('submitWsLicenseActivate')) {
            $this->wsHandleActivate();
        } elseif (\Tools::isSubmit('submitWsLicenseDeactivate')) {
            $this->wsHandleDeactivate();
        } elseif (\Tools::isSubmit('submitWsCheckUpdate')) {
            $this->wsHandleCheckUpdate();
        } elseif (\Tools::isSubmit('submitWsInstallUpdate')) {
            $this->wsHandleInstallUpdate();
        }
    }

    /**
     * Returns the rendered HTML for the license & update tab.
     *
     * @param string $adminUrl Target URL for all form POST actions (should include
     *                         current tab query parameter so the user stays on it)
     */
    public function wsRenderLicenseTab(string $adminUrl): string
    {
        if ($this->wsUpdateClient === null) {
            return '<div class="alert alert-danger">'
                . htmlspecialchars(
                    Translations::trans('Update client unavailable.', $this->wsLocale),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</div>';
        }

        $renderer = new LicenseTabRenderer(
            $this->wsUpdateClient,
            $this->wsLocale,
            $this->wsGetModuleName()
        );

        return $renderer->render(
            $adminUrl,
            $this->wsLicenseErrors,
            $this->wsLicenseConfirmations
        );
    }

    /**
     * Returns an admin notification banner HTML if a newer version is available.
     * Reads ONLY from the local Configuration cache — no HTTP request is made.
     * Returns an empty string when up to date or when the client is unavailable.
     *
     * Call this from hookDisplayAdminAfterHeader so the banner appears inside
     * #content on every admin page (same place as native PrestaShop alerts),
     * not only on the module configuration page:
     *
     *   public function hookDisplayAdminAfterHeader(): string
     *   {
     *       $tabUrl = $this->context->link->getAdminLink('AdminWsMyModule') . '&ws_tab=license';
     *       return $this->wsRenderUpdateNotification($tabUrl);
     *   }
     *
     * @param string $tabUrl URL of the license/update admin tab
     */
    public function wsRenderUpdateNotification(string $tabUrl): string
    {
        if ($this->wsUpdateClient === null) {
            return '';
        }

        // checkForUpdates(false) uses a 24-hour cache for the HTTP request — it only
        // contacts the server when the cache is stale. The notification itself is not
        // time-gated: it renders on every page load whenever an update is available.
        $info          = $this->wsUpdateClient->checkForUpdates(false);
        $latestVersion = $info['version'] ?? null;

        if (!$latestVersion) {
            return '';
        }

        if (!version_compare($latestVersion, $this->wsUpdateClient->getCurrentVersion(), '>')) {
            return '';
        }

        $moduleName    = htmlspecialchars($this->wsGetModuleName(), ENT_QUOTES, 'UTF-8');
        $latestSafe    = htmlspecialchars($latestVersion, ENT_QUOTES, 'UTF-8');
        $tabUrlSafe    = htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8');
        $msgUpdate     = htmlspecialchars(
            Translations::trans('Update available!', $this->wsLocale),
            ENT_QUOTES,
            'UTF-8'
        );
        $msgNew        = htmlspecialchars(
            Translations::trans('New version available: %s', $this->wsLocale, [$latestVersion]),
            ENT_QUOTES,
            'UTF-8'
        );
        $msgInstall    = htmlspecialchars(
            Translations::trans('Install update', $this->wsLocale),
            ENT_QUOTES,
            'UTF-8'
        );

        return '<div class="alert alert-warning">'
            . '<strong>' . $msgUpdate . '</strong>'
            . ' &mdash; <em>' . $moduleName . '</em>: ' . $msgNew
            . ' &nbsp;<a href="' . $tabUrlSafe . '" class="btn btn-sm btn-warning" style="margin-left:8px">'
            . '<i class="icon-upload"></i> ' . $msgInstall
            . '</a>'
            . '</div>' . "\n";
    }

    /**
     * Returns accumulated error messages (after wsHandleLicenseActions).
     *
     * @return string[]
     */
    public function wsGetLicenseErrors(): array
    {
        return $this->wsLicenseErrors;
    }

    /**
     * Returns accumulated success messages (after wsHandleLicenseActions).
     *
     * @return string[]
     */
    public function wsGetLicenseConfirmations(): array
    {
        return $this->wsLicenseConfirmations;
    }

    // -------------------------------------------------------------------------
    // Action handlers
    // -------------------------------------------------------------------------

    private function wsHandleActivate(): void
    {
        if ($this->wsUpdateClient === null) {
            $this->wsLicenseErrors[] = $this->wsT('Update client unavailable.');

            return;
        }

        $key = trim((string) \Tools::getValue('ws_license_key', ''));

        if ($key === '') {
            $this->wsLicenseErrors[] = $this->wsT('Please enter a license key.');

            return;
        }

        $result = $this->wsUpdateClient->activate($key);

        if ($result['success']) {
            $this->wsLicenseConfirmations[] = $this->wsT('License activated successfully.');
        } else {
            $msg = isset($result['data']['message'])
                ? $result['data']['message']
                : ($result['error'] ?? $this->wsT('Update client unavailable.'));
            $this->wsLicenseErrors[] = $this->wsT('License activation failed:') . ' ' . $msg;
        }
    }

    private function wsHandleDeactivate(): void
    {
        if ($this->wsUpdateClient === null) {
            $this->wsLicenseErrors[] = $this->wsT('Update client unavailable.');

            return;
        }

        $result = $this->wsUpdateClient->deactivate();

        if ($result['success']) {
            $this->wsLicenseConfirmations[] = $this->wsT('License deactivated.');
        } else {
            $msg = isset($result['data']['message'])
                ? $result['data']['message']
                : ($result['error'] ?? $this->wsT('Update client unavailable.'));
            $this->wsLicenseErrors[] = $this->wsT('License deactivation failed:') . ' ' . $msg;
        }
    }

    private function wsHandleCheckUpdate(): void
    {
        if ($this->wsUpdateClient === null) {
            $this->wsLicenseErrors[] = $this->wsT('Update client unavailable.');

            return;
        }

        $info = $this->wsUpdateClient->checkForUpdates(true);

        if ($info && !empty($info['version'])) {
            if (version_compare($info['version'], $this->wsUpdateClient->getCurrentVersion(), '>')) {
                $this->wsLicenseConfirmations[] = $this->wsT(
                    'New version available: %s',
                    [htmlspecialchars($info['version'], ENT_QUOTES, 'UTF-8')]
                );
            } else {
                $this->wsLicenseConfirmations[] = $this->wsT('Module is up to date.');
            }
        } else {
            $this->wsLicenseErrors[] = $this->wsT('Failed to check for updates. Please try again later.');
        }
    }

    private function wsHandleInstallUpdate(): void
    {
        if ($this->wsUpdateClient === null) {
            $this->wsLicenseErrors[] = $this->wsT('Update client unavailable.');

            return;
        }

        $info = $this->wsUpdateClient->checkForUpdates(true);

        if (empty($info['download_url']) || empty($info['version'])) {
            $this->wsLicenseErrors[] = $this->wsT('No update available.');

            return;
        }

        if (!version_compare($info['version'], $this->wsUpdateClient->getCurrentVersion(), '>')) {
            $this->wsLicenseConfirmations[] = $this->wsT('Module is already up to date.');

            return;
        }

        // Download the package ZIP to a temporary file
        $tmpZip = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'ws_update_' . $this->wsGetModuleName() . '_' . time() . '.zip';

        if (!$this->wsUpdateClient->downloadPackage($info['download_url'], $tmpZip)) {
            $this->wsLicenseErrors[] = $this->wsT('Failed to download update package.');

            return;
        }

        if (!class_exists('ZipArchive')) {
            @unlink($tmpZip);
            $this->wsLicenseErrors[] = $this->wsT('PHP ZipArchive extension is not available.');

            return;
        }

        $zip    = new \ZipArchive();
        $opened = $zip->open($tmpZip);

        if ($opened !== true) {
            @unlink($tmpZip);
            $this->wsLicenseErrors[] = $this->wsT('Failed to open update package.');

            return;
        }

        $extracted = $zip->extractTo(_PS_MODULE_DIR_);
        $zip->close();
        @unlink($tmpZip);

        if (!$extracted) {
            $this->wsLicenseErrors[] = $this->wsT('Failed to extract update package.');

            return;
        }

        $moduleName = $this->wsGetModuleName();
        $newVersion = (string) $info['version'];

        // ── Update ps_module.version FIRST ────────────────────────────────────
        // runUpgradeModule() compares ps_module.version (DB) with the version
        // from the module class file. After extraction the class file has the
        // new version, but PHP still holds the old class in memory.
        // By writing $newVersion into the DB first, runUpgradeModule() will
        // see "DB version == new file version" → nothing to do.
        // So instead we manually include and run each upgrade script ourselves.
        $upgradeDir = _PS_MODULE_DIR_ . $moduleName . '/upgrade/';

        if (is_dir($upgradeDir)) {
            $currentVersion = (string) \Db::getInstance()->getValue(
                'SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE name = \'' . pSQL($moduleName) . '\''
            );

            $files = glob($upgradeDir . 'upgrade-*.php');
            if (is_array($files)) {
                natsort($files);
                foreach ($files as $file) {
                    // Extract version from filename: upgrade-1.2.3.php → 1.2.3
                    if (!preg_match('/upgrade-([0-9]+(?:\.[0-9]+)*)\.php$/', basename($file), $m)) {
                        continue;
                    }
                    $scriptVersion = $m[1];
                    // Only run scripts newer than current DB version and up to (including) $newVersion
                    if (version_compare($scriptVersion, $currentVersion, '>')
                        && version_compare($scriptVersion, $newVersion, '<=')
                    ) {
                        include_once $file;
                        $fnName = 'upgrade_module_' . str_replace('.', '_', $scriptVersion);
                        if (function_exists($fnName)) {
                            $moduleInstance = \Module::getInstanceByName($moduleName);
                            $fnName($moduleInstance instanceof \Module ? $moduleInstance : $this);
                        }
                    }
                }
            }
        }

        // ── Update version in DB ──────────────────────────────────────────────
        \Db::getInstance()->update(
            'module',
            ['version' => pSQL($newVersion)],
            'name = \'' . pSQL($moduleName) . '\''
        );

        // ── Fire actionModuleUpgradeAfter ─────────────────────────────────────
        $moduleInstance = \Module::getInstanceByName($moduleName);
        if ($moduleInstance instanceof \Module) {
            \Hook::exec('actionModuleUpgradeAfter', [
                'object'  => $moduleInstance,
                'version' => $newVersion,
            ]);
        }

        // ── Best-effort cache clear ──────────────────────────────────────────
        if (method_exists('\Tools', 'clearAllCache')) {
            \Tools::clearAllCache();
        }

        @unlink(_PS_ROOT_DIR_ . '/var/cache/prod/class_index.php');
        @unlink(_PS_ROOT_DIR_ . '/var/cache/dev/class_index.php');
        @unlink(_PS_ROOT_DIR_ . '/cache/class_index.php');

        \PrestaShopLogger::addLog(
            sprintf(
                'WsUpdatePackage: module "%s" updated to version %s',
                $moduleName,
                $newVersion
            ),
            1
        );

        $this->wsLicenseConfirmations[] = $this->wsT(
            'Module successfully updated to version %s. Please refresh the page.',
            [htmlspecialchars($newVersion, ENT_QUOTES, 'UTF-8')]
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Translates $key using the detected locale.
     *
     * @param string   $key
     * @param string[] $params
     */
    private function wsT(string $key, array $params = []): string
    {
        return Translations::trans($key, $this->wsLocale, $params);
    }

    /**
     * Returns the module name from $this->module if available.
     */
    private function wsGetModuleName(): string
    {
        if (property_exists($this, 'module') && $this->module instanceof \Module) {
            return $this->module->name;
        }

        return '';
    }
}
