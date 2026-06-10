# Portar File Attachments para start_kit

Fonte:

```txt
D:\laragon\www\Projetos\bionexispeptides\app
```

Destino:

```txt
D:\laragon\www\Projetos\.a____LARAVEL\start_kit\start_kit
```

## Comando de coleta

Rode no PowerShell. Ele copia os arquivos isolados do modulo e cria snapshots dos arquivos compartilhados para merge manual. Nao sobrescreve `bootstrap/providers.php`, `routes/web.php`, `config/navigation.php` nem layouts.

```powershell
$src = 'D:\laragon\www\Projetos\bionexispeptides\app'
$dst = 'D:\laragon\www\Projetos\.a____LARAVEL\start_kit\start_kit'
$snapshot = Join-Path $dst '_port_uploads_from_bionexis'

New-Item -ItemType Directory -Force $snapshot | Out-Null

robocopy "$src\app\Modules\Uploads" "$dst\app\Modules\Uploads" /E /XD .git node_modules vendor
robocopy "$src\resources\js\modules\uploads" "$dst\resources\js\modules\uploads" /E /XD .git node_modules vendor

Copy-Item "$src\resources\js\pages\admin\dev\components\file-attachments.tsx" "$dst\resources\js\pages\admin\dev\components\file-attachments.tsx" -Force
Copy-Item "$src\resources\js\components\delete-confirm-modal.tsx" "$dst\resources\js\components\delete-confirm-modal.tsx" -Force
Copy-Item "$src\resources\js\components\ui\switch.tsx" "$dst\resources\js\components\ui\switch.tsx" -Force

Copy-Item "$src\bootstrap\providers.php" "$snapshot\providers.bionexis.php" -Force
Copy-Item "$src\resources\js\layouts\app\app-sidebar-layout.tsx" "$snapshot\app-sidebar-layout.bionexis.tsx" -Force
Copy-Item "$src\routes\web.php" "$snapshot\web.bionexis.php" -Force
Copy-Item "$src\config\navigation.php" "$snapshot\navigation.bionexis.php" -Force
Copy-Item "$src\tests\Feature\UploadDownloadTest.php" "$snapshot\UploadDownloadTest.bionexis.php" -Force

Set-Location $dst
npm install zustand
composer dump-autoload
php artisan wayfinder:generate --with-form --no-interaction
```

`robocopy` pode retornar codigo `1` quando copia arquivos com sucesso. Trate `0` e `1` como OK.

## Copia direta

Estes arquivos podem ir direto para o `start_kit`:

- `app/Modules/Uploads/`
- `resources/js/modules/uploads/`
- `resources/js/pages/admin/dev/components/file-attachments.tsx`
- `resources/js/components/delete-confirm-modal.tsx`
- `resources/js/components/ui/switch.tsx`, necessario apenas para a pagina dev

O `start_kit` ja tem:

- `resources/js/lib/http.ts` com o contrato esperado pelo modulo
- `@tanstack/react-query` e `QueryClientProvider`
- `lucide-react`
- Sonner/Toaster
- componentes UI principais: `button`, `input`, `checkbox`, `dialog`, `collapsible`, `command`, `popover`, `dropdown-menu`

Falta no `package.json` atual do `start_kit`:

- `zustand`, usado por `resources/js/modules/uploads/stores/zip-download-store.ts`

## Merge obrigatorio no destino

### 1. Registrar provider

Em `bootstrap/providers.php`, adicionar:

```php
use App\Modules\Uploads\Providers\UploadsServiceProvider;
```

E incluir `UploadsServiceProvider::class` no array de providers.

### 2. Ajustar aliases do modulo

Editar `app/Modules/Uploads/Config/uploads.php`.

O arquivo copiado vem com aliases do Bionexis:

```php
'product' => Product::class,
'product-lot' => ProductLot::class,
'billing-payment' => BillingPayment::class,
```

No `start_kit`, troque por models reais do destino. Exemplo:

```php
use App\Modules\Clients\Models\Client;

'aliases' => [
    'client' => Client::class,
],
```

Tambem remova `public_rules` que apontem para aliases inexistentes, ou adapte para o model local.

### 3. Desacoplar regras de dominio do Bionexis

`app/Modules/Uploads/Services/UploadService.php` e `app/Modules/Uploads/Policies/UploadedPolicy.php` citam `Product`, `ProductLot`, `BillingPayment`, `RecordBillingEventAction` e permissoes de financeiro.

No `start_kit`, ajuste para uma regra generica. Caminho recomendado:

- `canViewParent`: retornar `$user->can('view', $parentable)` quando existir policy do parent, ou fallback para dono/uploader.
- `canCreateForParent`: retornar `$user->can('update', $parentable)` quando existir policy do parent.
- remover o bloco que grava evento de `BillingPayment`.
- `pathIdentifierFor`: usar `uuid` se o model tiver esse atributo, senao usar `getKey()`.

### 4. Montar painel global de ZIP

Se quiser manter ZIP em background, adicionar em `resources/js/layouts/app/app-sidebar-layout.tsx`:

```tsx
import { ZipDownloadPanel } from '@/modules/uploads';
```

E renderizar dentro de `AppShell`:

```tsx
<ZipDownloadPanel />
```

Se nao quiser ZIP na primeira fase, remova temporariamente o painel e o fluxo de ZIP da UI ou mantenha sem expor a acao.

### 5. Registrar pagina dev

Em `app/Http/Controllers/Admin/DevToolsController.php`, adicionar:

```php
public function fileAttachments(): Response
{
    return Inertia::render('admin/dev/components/file-attachments');
}
```

Em `routes/web.php`, dentro de `admin.dev.components`, adicionar:

```php
Route::get('/file-attachments', [DevToolsController::class, 'fileAttachments'])->name('file-attachments');
```

Depois rode:

```powershell
php artisan wayfinder:generate --with-form --no-interaction
```

A pagina copiada importa `fileAttachments` de `@/actions/App/Http/Controllers/Admin/DevToolsController`; sem regenerar Wayfinder, esse import nao existe.

### 6. Navegacao dev

Em `config/navigation.php`, dentro do grupo `componentes`, adicionar um item apontando para:

```php
['route' => 'admin.dev.components.file-attachments']
```

Use um icone ja resolvido por `resources/js/lib/nav-icons.ts`, por exemplo `Paperclip`.

## Testes

O teste `tests/Feature/UploadDownloadTest.php` do Bionexis nao deve ser copiado direto para `tests/Feature` sem ajuste, porque depende de `Product`, `BillingPayment` e comandos especificos de produto.

Use o snapshot criado em:

```txt
_port_uploads_from_bionexis\UploadDownloadTest.bionexis.php
```

E recrie um teste local com `Client` ou outro model real do `start_kit`, cobrindo:

- listagem de anexos autorizada
- upload de arquivo pequeno
- bloqueio por alias inexistente
- preview/download protegido
- delete autorizado
- limite de tamanho
- ZIP apenas se o worker/painel forem mantidos

Comandos de validacao apos merge:

```powershell
php artisan migrate --no-interaction
php artisan route:list --except-vendor | Select-String uploads
php artisan test --compact --filter=Upload
npm run types:check
```

Depois de alterar PHP:

```powershell
vendor/bin/pint --dirty --format agent
```

## Arquivos que nao entram no pacote inicial

Nao copie automaticamente:

- `app/Console/Commands/ProductUploadPathsToUuidCommand.php`
- `app/Console/Commands/PrivatizeUploadsCommand.php`
- `resources/js/pages/app/finance/billings/components/*`
- `tests/Feature/BillingModuleTest.php`

Esses arquivos sao integracoes de dominio do Bionexis, nao o nucleo do componente File Attachments. Se precisar de privatizacao de uploads depois, porte `PrivatizeUploadsCommand` separadamente e adapte aliases/permissoes.

## Pontos de risco

- Autorizacao: o modulo nao pode depender apenas de esconder botoes no React; preview, download, update, delete, ZIP e bulk delete precisam validar no servidor.
- Aliases: nunca envie classe PHP pelo frontend. Use aliases em `uploads.php`.
- Storage: uploads sensiveis devem ficar no disk privado (`local`) e sair por rotas protegidas.
- ZIP: exige queue worker e extensao PHP `zip`.
- Texto da pagina dev: o arquivo fonte esta com alguns caracteres quebrados; revise strings visiveis depois da copia.
- Rotas: API usa `auth:sanctum`; se o destino usar sessao web para chamadas Inertia, confirme se Sanctum esta configurado para SPA/same-origin.

## Sequencia recomendada

1. Rodar o comando de coleta.
2. Registrar provider.
3. Ajustar `uploads.php`.
4. Remover acoplamentos Bionexis de `UploadService` e `UploadedPolicy`.
5. Rodar migration e route:list.
6. Regenerar Wayfinder.
7. Adicionar pagina dev e navegacao, se quiser playground.
8. Escrever teste local com model do `start_kit`.
9. Rodar teste focado, Pint e TypeScript.
