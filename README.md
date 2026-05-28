# websystems/ws-prestashop-update-package

Paczka Composer do zarządzania **licencjami** i **aktualizacjami** modułów PrestaShop przez serwer UpdatePulse. Zamiast duplikować kod w każdym module, wystarczy dołączyć tę paczkę i użyć gotowego traita.

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

### 1. Użyj traita w kontrolerze admina

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

### 2. Dodaj zakładkę "Licencja i aktualizacje" w szablonie

W szablonie Smarty (np. `configuration.tpl`) dodaj link do zakładki i wyświetl wygenerowany HTML:

```smarty
{* Nagłówek zakładek *}
<a href="{$admin_module_url}&ws_tab=license" class="btn btn-default">
    Licencja i aktualizacje
</a>

{* Treść zakładki *}
{if $ws_tab === 'license'}
    {$ws_license_tab_html|nofilter}
{/if}
```

Lub przypisz HTML z poziomu kontrolera i wyświetl bezpośrednio:

```php
$this->context->smarty->assign('ws_license_tab_html', $this->wsRenderLicenseTab($adminUrl));
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

1. Paczka odpytuje serwer (`updatepulse-server-update-api`, akcja `get_metadata`) raz na 24 h.
2. Wynik jest cache'owany w `Configuration` (`WS_MYMODULE_UPDATE_INFO`, `WS_MYMODULE_UPDATE_LAST_CHECK`).
3. Gdy dostępna jest nowsza wersja, w zakładce pojawia się przycisk **Zainstaluj aktualizację**.
4. Instalacja pobiera ZIP z serwera przez cURL (tylko HTTPS), rozpakowuje go do `_PS_MODULE_DIR_` i czyści cache PrestaShop.

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
    WsLicenseTrait.php        ← trait do wklejenia w AdminController
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

