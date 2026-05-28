# websystems/ws-prestashop-update-package

Paczka Composer do zarządzania **licencjami** i **aktualizacjami** modułów PrestaShop przez serwer UpdatePulse. Zamiast duplikować kod w każdym module, wystarczy dołączyć tę paczkę i użyć gotowego traita.

---

## Wersja 1.0.4

---

## Wymagania

- PHP >= 7.2
- Rozszerzenia PHP: `ext-curl`, `ext-zip`
- PrestaShop 1.7 lub 8.x
- Plik `updatepulse.json` w katalogu głównym modułu

---

## Instalacja

```bash
composer require websystems/ws-prestashop-update-package
```

Następnie upewnij się, że autoload Composera jest ładowany w module (zwykle już jest w `vendor/autoload.php`).

---

## Plik updatepulse.json

Każdy moduł musi posiadać plik `updatepulse.json` w swoim katalogu głównym (np. `modules/ws_mymodule/updatepulse.json`):

```json
{
    "server": "https://admin.k4.pl",
    "packageData": {
        "Name": "Nazwa modułu",
        "Version": "1.0.0",
        "Homepage": "https://admin.k4.pl/",
        "Author": "Web Systems",
        "AuthorURI": "https://admin.k4.pl/",
        "Description": "Opis modułu",
        "RequireLicense": true
    }
}
```

Opcjonalnie można dodać pole `"Slug"` w `packageData` — jeśli go nie ma, używana jest nazwa modułu (`$module->name`).

---

## Integracja z modułem

Trait `WsLicenseTrait` można użyć w **dwóch miejscach** niezależnie lub razem:

| Miejsce | Do czego |
|---|---|
| Klasa modułu (`Module`) | Globalny baner powiadomień o aktualizacji |
| Kontroler admina (`ModuleAdminController`) | Pełna zakładka „Licencja i aktualizacje" |

---

### 1. Globalny baner powiadomień (klasa modułu)

Dzięki temu baner o dostępnej aktualizacji pojawia się **na każdej stronie admina** — wewnątrz `#content`, tak samo jak natywne komunikaty PrestaShop — a nie tylko w zakładce konfiguracji modułu.

```php
class Ws_mymodule extends Module
{
    use \Websystems\PrestashopUpdatePackage\Traits\WsLicenseTrait;

    public function __construct()
    {
        // ...
        parent::__construct();
        // ...

        // Inicjalizacja klienta aktualizacji
        $this->wsInitUpdateManager($this, 'WS_MYMODULE');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayAdminAfterHeader');
            // ... pozostałe hooki
    }

    /**
     * Renderuje baner z informacją o dostępnej aktualizacji.
     * Sprawdzanie serwera odbywa się max raz na 24 h (cache w Configuration).
     * Sam baner pokazuje się zawsze gdy wykryto nowszą wersję — bez limitu czasowego.
     */
    public function hookDisplayAdminAfterHeader(): string
    {
        $tabUrl = $this->context->link->getAdminLink('AdminWsMyModule') . '&ws_tab=license';
        return $this->wsRenderUpdateNotification($tabUrl);
    }
}
```

---

### 2. Zakładka „Licencja i aktualizacje" (kontroler admina)

```php
use Websystems\PrestashopUpdatePackage\Traits\WsLicenseTrait;

class AdminWsMyModuleController extends ModuleAdminController
{
    use WsLicenseTrait;

    public function __construct()
    {
        parent::__construct();
        // Inicjalizacja — przekaż instancję modułu i prefiks klucza konfiguracyjnego
        $this->wsInitUpdateManager($this->module, 'WS_MYMODULE');
    }

    public function postProcess()
    {
        // Obsługuje submitWsLicenseActivate, submitWsLicenseDeactivate,
        // submitWsCheckUpdate, submitWsInstallUpdate
        $this->wsHandleLicenseActions();
        parent::postProcess();
    }

    public function renderView()
    {
        if (Tools::getValue('ws_tab') === 'license') {
            $adminUrl = $this->context->link->getAdminLink('AdminWsMyModule') . '&ws_tab=license';
            return $this->wsRenderLicenseTab($adminUrl);
        }
        return parent::renderView();
    }
}
```

---

## Jak działa zarządzanie licencjami

1. Admin wpisuje klucz licencji w formularzu i klika **Aktywuj licencję**.
2. Paczka wysyła żądanie do serwera UpdatePulse (`updatepulse-server-license-api`).
3. Serwer weryfikuje klucz i domenę sklepu — w odpowiedzi odsyła `license_signature`.
4. Klucz i sygnatura są zapisywane w `Configuration` PrestaShop (klucze: `WS_MYMODULE_LICENSE_KEY`, `WS_MYMODULE_LICENSE_SIG`).
5. Dezaktywacja usuwa dane z `Configuration` i informuje serwer.

---

## Jak działają aktualizacje

1. Paczka odpytuje serwer (`updatepulse-server-update-api`, akcja `get_metadata`) **max raz na 24 h** (cache w `Configuration`).
2. Wynik jest cache'owany — klucze `WS_MYMODULE_UPDATE_INFO` (JSON) i `WS_MYMODULE_UPDATE_LAST_CHECK` (timestamp).
3. Baner powiadomienia (`hookDisplayAdminAfterHeader`) wyświetla się na każdej stronie admina gdy dostępna jest nowsza wersja; sam baner nie ma żadnego limitu czasowego.
4. W zakładce „Licencja i aktualizacje" pojawia się przycisk **Zainstaluj aktualizację** oraz link do pobrania ZIP.
5. Instalacja jest wyłącznie ręczna — tylko gdy admin kliknie przycisk. Pobiera ZIP przez cURL (wyłącznie HTTPS), rozpakowuje do `_PS_MODULE_DIR_`, uruchamia skrypty upgrade z katalogu `upgrade/`, aktualizuje wersję w `ps_module`.
6. Przycisk **Sprawdź teraz** w zakładce wymusza natychmiastowe odpytanie serwera (pomija cache).

---

## Klucze konfiguracyjne w bazie danych

Klucze są budowane automatycznie z przekazanego prefiksu (np. `WS_MYMODULE`):

| Klucz | Zawartość |
|---|---|
| `WS_MYMODULE_LICENSE_KEY` | Klucz licencji |
| `WS_MYMODULE_LICENSE_SIG` | Sygnatura licencji (z serwera) |
| `WS_MYMODULE_UPDATE_INFO` | JSON z metadanymi ostatniej odpowiedzi serwera |
| `WS_MYMODULE_UPDATE_LAST_CHECK` | Timestamp ostatniego sprawdzenia aktualizacji |

---

## Struktura paczki

```
src/
  Update/
    UpdatePulseClient.php     ← klient HTTP do serwera UpdatePulse
  License/
    LicenseTabRenderer.php    ← generator HTML zakładki "Licencja i aktualizacje"
  Traits/
    WsLicenseTrait.php        ← trait do wklejenia w AdminController (zakładka licencji)
  Translations.php            ← tłumaczenia PL/EN (tablice statyczne)
```

---

## Tłumaczenia

Paczka zawiera wbudowane tłumaczenia PL i EN. Język wykrywany jest automatycznie z `Context::getContext()->language->iso_code`. Można też użyć bezpośrednio:

```php
use Websystems\PrestashopUpdatePackage\Translations;

$msg = Translations::trans('License activated successfully.', 'pl');
// → "Licencja aktywowana pomyślnie."
```

---

## Autor

**Artur Ograbek** — [a.ograbek@web-systems.pl](mailto:a.ograbek@web-systems.pl)  
Web Systems — [https://admin.k4.pl](https://admin.k4.pl)

