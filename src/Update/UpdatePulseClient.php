<?php

namespace Websystems\PrestashopUpdatePackage\Update;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * UpdatePulse Server — Generic PHP/cURL client for PrestaShop modules.
 *
 * Usage:
 *   $client = new UpdatePulseClient($this->module, 'WS_MYMODULE');
 *
 * Requires updatepulse.json in the module root directory.
 */
class UpdatePulseClient
{
    /** Minimum interval between automatic update checks (seconds) — 24 h */
    const CHECK_INTERVAL = 86400;

    /** Update endpoint slug */
    const ENDPOINT_UPDATE = 'updatepulse-server-update-api';

    /** License endpoint slug */
    const ENDPOINT_LICENSE = 'updatepulse-server-license-api';

    /** @var string */
    private $serverUrl;

    /** @var string Package slug read from updatepulse.json */
    private $packageSlug;

    /** @var string Current installed version */
    private $currentVersion;

    /** @var string Stored license key */
    private $licenseKey;

    /** @var string Stored license signature */
    private $licenseSignature;

    /** @var string Shop domain used as the allowed domain */
    private $allowedDomain;

    /** @var string PrestaShop Configuration key — license key */
    private $cfgLicenseKey;

    /** @var string PrestaShop Configuration key — license signature */
    private $cfgLicenseSig;

    /** @var string PrestaShop Configuration key — cached update info (JSON) */
    private $cfgUpdateInfo;

    /** @var string PrestaShop Configuration key — last check timestamp */
    private $cfgLastCheck;

    /**
     * @param \Module $module        The PrestaShop module instance.
     * @param string  $configPrefix  Prefix for Configuration keys, e.g. 'WS_MYMODULE'.
     *                               Underscore suffix is normalised automatically.
     *
     * @throws \RuntimeException when updatepulse.json is missing or invalid.
     */
    public function __construct(\Module $module, string $configPrefix)
    {
        $prefix = rtrim($configPrefix, '_');

        $this->cfgLicenseKey = $prefix . '_LICENSE_KEY';
        $this->cfgLicenseSig = $prefix . '_LICENSE_SIG';
        $this->cfgUpdateInfo = $prefix . '_UPDATE_INFO';
        $this->cfgLastCheck  = $prefix . '_UPDATE_LAST_CHECK';

        $config = $this->loadConfig($module->name);

        $this->serverUrl        = rtrim($config['server'], '/');
        $this->packageSlug      = $config['packageData']['Slug'] ?? $module->name;
        $this->currentVersion   = $config['packageData']['Version'];
        $this->licenseKey       = (string) \Configuration::get($this->cfgLicenseKey);
        $this->licenseSignature = (string) \Configuration::get($this->cfgLicenseSig);
        $this->allowedDomain    = \Tools::getShopDomainSsl() ?: \Tools::getShopDomain();
    }

    // -------------------------------------------------------------------------
    // Config loader
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function loadConfig(string $moduleName): array
    {
        $path = _PS_MODULE_DIR_ . $moduleName . '/updatepulse.json';

        if (!file_exists($path)) {
            throw new \RuntimeException('UpdatePulse: configuration file not found: ' . $path);
        }

        $content = file_get_contents($path);
        $config  = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE
            || empty($config['server'])
            || empty($config['packageData']['Version'])
        ) {
            throw new \RuntimeException('UpdatePulse: invalid configuration file at ' . $path);
        }

        return $config;
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    /**
     * Sends a GET request to the UpdatePulse endpoint.
     *
     * @param string               $endpoint  Endpoint slug (without leading slash)
     * @param array<string, mixed> $args      Query-string parameters
     * @param int                  $timeout   Request timeout in seconds
     *
     * @return array{success: bool, data?: array<string, mixed>, http_code?: int, error?: string}
     */
    private function sendRequest(string $endpoint, array $args, int $timeout = 20): array
    {
        $url = $this->serverUrl . '/' . $endpoint . '/?' . http_build_query($args);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'cURL: ' . $curlError];
        }

        if (false === $response || '' === $response) {
            return ['success' => false, 'error' => 'Empty server response.', 'http_code' => $httpCode];
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid JSON response.', 'http_code' => $httpCode];
        }

        return [
            'success'   => $httpCode >= 200 && $httpCode < 300,
            'data'      => $data,
            'http_code' => $httpCode,
        ];
    }

    // -------------------------------------------------------------------------
    // Update API
    // -------------------------------------------------------------------------

    /**
     * Fetches package metadata from the UpdatePulse server.
     * Results are cached for CHECK_INTERVAL seconds; pass $force = true to bypass.
     *
     * @return array<string, mixed>|null
     */
    public function checkForUpdates(bool $force = false): ?array
    {
        $lastCheck = (int) \Configuration::get($this->cfgLastCheck);

        if (!$force && (time() - $lastCheck) < self::CHECK_INTERVAL) {
            $cached = \Configuration::get($this->cfgUpdateInfo);

            return $cached ? json_decode($cached, true) : null;
        }

        $args = [
            'action'            => 'get_metadata',
            'package_id'        => $this->packageSlug,
            'installed_version' => $this->currentVersion,
            'update_type'       => 'Generic',
        ];

        if ($this->licenseKey !== '') {
            $args['license_key']       = $this->licenseKey;
            $args['license_signature'] = $this->licenseSignature;
        }

        $result = $this->sendRequest(self::ENDPOINT_UPDATE, $args);

        if (!$result['success'] || empty($result['data'])) {
            return null;
        }

        $info = $result['data'];
        \Configuration::updateValue($this->cfgUpdateInfo, json_encode($info));
        \Configuration::updateValue($this->cfgLastCheck, (string) time());

        return $info;
    }

    /**
     * Returns true when a newer version is available on the server.
     */
    public function isUpdateAvailable(): bool
    {
        $info = $this->checkForUpdates();

        if (!$info || empty($info['version'])) {
            return false;
        }

        return version_compare($info['version'], $this->currentVersion, '>');
    }

    /**
     * Returns the latest version string from the server, or null on error.
     */
    public function getLatestVersion(): ?string
    {
        $info = $this->checkForUpdates();

        return isset($info['version']) ? (string) $info['version'] : null;
    }

    /**
     * Returns the download URL for the update package, or null when unavailable.
     */
    public function getDownloadUrl(): ?string
    {
        $info = $this->checkForUpdates();

        return isset($info['download_url']) ? (string) $info['download_url'] : null;
    }

    /**
     * Returns the current installed version (from updatepulse.json).
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    // -------------------------------------------------------------------------
    // License API
    // -------------------------------------------------------------------------

    /**
     * Activates the license for the current shop domain.
     * On success, persists the key and signature to PrestaShop Configuration.
     *
     * @return array{success: bool, data?: array<string, mixed>, http_code?: int, error?: string}
     */
    public function activate(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            return ['success' => false, 'error' => 'License key cannot be empty.'];
        }

        $args = [
            'action'          => 'activate',
            'license_key'     => $licenseKey,
            'allowed_domains' => $this->allowedDomain,
            'package_slug'    => $this->packageSlug,
        ];

        $result = $this->sendRequest(self::ENDPOINT_LICENSE, $args, 30);

        if ($result['success'] && !empty($result['data']['license_signature'])) {
            $signature = rawurldecode($result['data']['license_signature']);
            \Configuration::updateValue($this->cfgLicenseKey, $licenseKey);
            \Configuration::updateValue($this->cfgLicenseSig, $signature);
            $this->licenseKey       = $licenseKey;
            $this->licenseSignature = $signature;
            // Invalidate update cache so next check uses the active license
            \Configuration::deleteByName($this->cfgUpdateInfo);
            \Configuration::deleteByName($this->cfgLastCheck);
        }

        return $result;
    }

    /**
     * Deactivates the license on the current domain and removes all stored data.
     *
     * @return array{success: bool, data?: array<string, mixed>, http_code?: int, error?: string}
     */
    public function deactivate(): array
    {
        if ($this->licenseKey === '') {
            return ['success' => false, 'error' => 'No active license.'];
        }

        $args = [
            'action'          => 'deactivate',
            'license_key'     => $this->licenseKey,
            'allowed_domains' => $this->allowedDomain,
            'package_slug'    => $this->packageSlug,
        ];

        $result = $this->sendRequest(self::ENDPOINT_LICENSE, $args, 30);

        if ($result['success']) {
            \Configuration::deleteByName($this->cfgLicenseKey);
            \Configuration::deleteByName($this->cfgLicenseSig);
            \Configuration::deleteByName($this->cfgUpdateInfo);
            \Configuration::deleteByName($this->cfgLastCheck);
            $this->licenseKey       = '';
            $this->licenseSignature = '';
        }

        return $result;
    }

    /**
     * Returns the current license status without making any server calls.
     *
     * @return array{has_license: bool, license_key_masked: string, is_activated: bool, domain: string}
     */
    public function getLicenseStatus(): array
    {
        return [
            'has_license'        => $this->licenseKey !== '',
            'license_key_masked' => $this->licenseKey !== ''
                ? substr($this->licenseKey, 0, 8) . str_repeat('*', max(0, strlen($this->licenseKey) - 8))
                : '',
            'is_activated'       => $this->licenseKey !== '' && $this->licenseSignature !== '',
            'domain'             => $this->allowedDomain,
        ];
    }

    // -------------------------------------------------------------------------
    // Package download
    // -------------------------------------------------------------------------

    /**
     * Downloads the update ZIP to the given path. HTTPS only.
     *
     * @param string $downloadUrl URL from the get_metadata response
     * @param string $destination Full local file path for the saved ZIP
     *
     * @return bool true on success
     */
    public function downloadPackage(string $downloadUrl, string $destination): bool
    {
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        // HTTPS only — reject plain HTTP downloads
        if (stripos($downloadUrl, 'https://') !== 0) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($this->licenseKey !== '') {
            $args = [
                'license_key'       => $this->licenseKey,
                'license_signature' => $this->licenseSignature,
            ];
            if (strpos($downloadUrl, 'license_key=') === false) {
                curl_setopt($ch, CURLOPT_URL, $downloadUrl . '&' . http_build_query($args));
            }
        }

        $content  = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$content) {
            return false;
        }

        return file_put_contents($destination, $content) !== false;
    }
}
