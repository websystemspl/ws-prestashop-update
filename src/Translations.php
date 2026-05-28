<?php

namespace Websystems\PrestashopUpdatePackage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Built-in PL/EN translations for the license & update tab.
 *
 * Usage:
 *   Translations::trans('License activated successfully.', 'pl')
 *   Translations::trans('New version available: %s', 'en', ['1.2.3'])
 */
class Translations
{
    /** @var array<string, array<string, string>> */
    private static $strings = [
        'en' => [
            // License messages
            'Please enter a license key.'                                               => 'Please enter a license key.',
            'License activated successfully.'                                           => 'License activated successfully.',
            'License activation failed:'                                                => 'License activation failed:',
            'License deactivated.'                                                      => 'License deactivated.',
            'License deactivation failed:'                                              => 'License deactivation failed:',
            'Update client unavailable.'                                                => 'Update client unavailable.',
            // Update messages
            'New version available: %s'                                                 => 'New version available: %s',
            'Module is up to date.'                                                     => 'Module is up to date.',
            'Failed to check for updates. Please try again later.'                      => 'Failed to check for updates. Please try again later.',
            'No update available.'                                                      => 'No update available.',
            'Module is already up to date.'                                             => 'Module is already up to date.',
            'Failed to download update package.'                                        => 'Failed to download update package.',
            'PHP ZipArchive extension is not available.'                                => 'PHP ZipArchive extension is not available.',
            'Failed to open update package.'                                            => 'Failed to open update package.',
            'Failed to extract update package.'                                         => 'Failed to extract update package.',
            'Module successfully updated to version %s. Please refresh the page.'      => 'Module successfully updated to version %s. Please refresh the page.',
            // UI — update section
            'Module updates'                                                            => 'Module updates',
            'Installed version'                                                         => 'Installed version',
            'Latest available version'                                                  => 'Latest available version',
            'Update available!'                                                         => 'Update available!',
            'Install update'                                                            => 'Install update',
            'Download ZIP'                                                              => 'Download ZIP',
            'Up to date'                                                                => 'Up to date',
            'Check for updates now'                                                     => 'Check for updates now',
            'This will replace module files automatically. Make sure you have a backup. Continue?'
                                                                                        => 'This will replace module files automatically. Make sure you have a backup. Continue?',
            // UI — license section
            'License & Updates'                                                         => 'License & Updates',
            'License'                                                                   => 'License',
            'Registered domain'                                                         => 'Registered domain',
            'License status'                                                            => 'License status',
            'Active'                                                                    => 'Active',
            'Key saved, not activated'                                                  => 'Key saved, not activated',
            'No license'                                                                => 'No license',
            'Deactivate license'                                                        => 'Deactivate license',
            'Are you sure you want to deactivate the license on this domain?'           => 'Are you sure you want to deactivate the license on this domain?',
            'License key'                                                               => 'License key',
            'Activate license'                                                          => 'Activate license',
            'License information unavailable (UpdatePulse client error).'              => 'License information unavailable (UpdatePulse client error).',
        ],
        'pl' => [
            // License messages
            'Please enter a license key.'                                               => 'Proszę wpisać klucz licencji.',
            'License activated successfully.'                                           => 'Licencja aktywowana pomyślnie.',
            'License activation failed:'                                                => 'Aktywacja licencji nie powiodła się:',
            'License deactivated.'                                                      => 'Licencja dezaktywowana.',
            'License deactivation failed:'                                              => 'Dezaktywacja licencji nie powiodła się:',
            'Update client unavailable.'                                                => 'Klient aktualizacji niedostępny.',
            // Update messages
            'New version available: %s'                                                 => 'Dostępna nowa wersja: %s',
            'Module is up to date.'                                                     => 'Moduł jest aktualny.',
            'Failed to check for updates. Please try again later.'                      => 'Nie udało się sprawdzić dostępnych aktualizacji. Spróbuj ponownie później.',
            'No update available.'                                                      => 'Brak dostępnej aktualizacji.',
            'Module is already up to date.'                                             => 'Moduł jest już aktualny.',
            'Failed to download update package.'                                        => 'Nie udało się pobrać pakietu aktualizacji.',
            'PHP ZipArchive extension is not available.'                                => 'Rozszerzenie PHP ZipArchive nie jest dostępne.',
            'Failed to open update package.'                                            => 'Nie udało się otworzyć pakietu aktualizacji.',
            'Failed to extract update package.'                                         => 'Nie udało się rozpakować pakietu aktualizacji.',
            'Module successfully updated to version %s. Please refresh the page.'      => 'Moduł pomyślnie zaktualizowany do wersji %s. Odśwież stronę.',
            // UI — update section
            'Module updates'                                                            => 'Aktualizacje modułu',
            'Installed version'                                                         => 'Zainstalowana wersja',
            'Latest available version'                                                  => 'Najnowsza dostępna wersja',
            'Update available!'                                                         => 'Dostępna aktualizacja!',
            'Install update'                                                            => 'Zainstaluj aktualizację',
            'Download ZIP'                                                              => 'Pobierz ZIP',
            'Up to date'                                                                => 'Aktualny',
            'Check for updates now'                                                     => 'Sprawdź teraz',
            'This will replace module files automatically. Make sure you have a backup. Continue?'
                                                                                        => 'Pliki modułu zostaną automatycznie zastąpione. Upewnij się, że masz kopię zapasową. Kontynuować?',
            // UI — license section
            'License & Updates'                                                         => 'Licencja i aktualizacje',
            'License'                                                                   => 'Licencja',
            'Registered domain'                                                         => 'Zarejestrowana domena',
            'License status'                                                            => 'Status licencji',
            'Active'                                                                    => 'Aktywna',
            'Key saved, not activated'                                                  => 'Klucz zapisany, nieaktywowany',
            'No license'                                                                => 'Brak licencji',
            'Deactivate license'                                                        => 'Dezaktywuj licencję',
            'Are you sure you want to deactivate the license on this domain?'           => 'Na pewno dezaktywować licencję na tej domenie?',
            'License key'                                                               => 'Klucz licencji',
            'Activate license'                                                          => 'Aktywuj licencję',
            'License information unavailable (UpdatePulse client error).'              => 'Informacje o licencji niedostępne (błąd klienta UpdatePulse).',
        ],
    ];

    /**
     * Returns the translated string for $key in the given $locale.
     * Falls back to English when the locale is not found.
     * Passes $params through vsprintf when the string contains format specifiers.
     *
     * @param string   $key    Translation key (the English source string)
     * @param string   $locale Language iso_code or locale string (e.g. 'pl', 'pl-PL', 'en')
     * @param string[] $params Optional sprintf-style parameters
     */
    public static function trans(string $key, string $locale, array $params = []): string
    {
        $lang = (strpos($locale, 'pl') === 0) ? 'pl' : 'en';
        $str  = self::$strings[$lang][$key] ?? self::$strings['en'][$key] ?? $key;

        if (!empty($params)) {
            $str = vsprintf($str, $params);
        }

        return $str;
    }

    /**
     * Detects the current locale from PrestaShop Context.
     * Returns 'en' as fallback.
     */
    public static function getLocale(): string
    {
        try {
            $context = \Context::getContext();
            if ($context && $context->language) {
                return (string) ($context->language->iso_code ?? 'en');
            }
        } catch (\Exception $e) {
            // ignore — fallback below
        }

        return 'en';
    }
}
