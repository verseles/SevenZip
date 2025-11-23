# Pontos de Melhoria Identificados - SevenZip PHP Library

Este documento contém uma análise detalhada dos pontos de melhoria identificados no projeto SevenZip.

---

## 1. Arquitetura e Estrutura de Codigo

### 1.1 Classe Monolitica (Alta Prioridade)
**Arquivo:** `src/SevenZip.php` (1.776 linhas)

A classe `SevenZip` concentra muitas responsabilidades. Recomenda-se aplicar o principio Single Responsibility (SRP):

**Sugestao de refatoracao:**
```
src/
├── SevenZip.php              # Facade principal (simplificada)
├── Compressor.php            # Logica de compressao
├── Extractor.php             # Logica de extracao
├── ArchiveInfo.php           # Parsing de informacoes de arquivos
├── CommandBuilder.php        # Construcao de comandos 7z
├── ProgressHandler.php       # Gerenciamento de progresso
├── Config/
│   └── FormatConfig.php      # Configuracoes de formato
└── Exceptions/
    ├── ExecutableNotFoundException.php
    ├── CompressionException.php
    ├── ExtractionException.php
    └── InvalidFormatException.php
```

### 1.2 Falta de Interfaces
Nao ha interfaces definidas, dificultando testes e extensibilidade:

```php
// Sugestao:
interface CompressorInterface {
    public function compress(): string;
}

interface ExtractorInterface {
    public function extract(): string;
}

interface ProgressCallbackInterface {
    public function onProgress(int $percentage): void;
}
```

### 1.3 Hierarquia de Excecoes Limitada
**Arquivo:** `src/Exceptions/ExecutableNotFoundException.php`

Apenas uma excecao customizada existe. Adicionar:

- `CompressionException` - erros durante compressao
- `ExtractionException` - erros durante extracao
- `InvalidPasswordException` - senha incorreta
- `InvalidFormatException` - formato nao suportado
- `TimeoutException` - timeout de operacao

---

## 2. Qualidade do Codigo

### 2.1 Codigo Comentado (Baixa Prioridade)
**Arquivo:** `src/SevenZip.php`

Linhas com codigo comentado que devem ser removidas ou tratadas:
- Linha 1130: `//    var_dump($lines);`
- Linha 1235: `//        ->addFlag('snon') // @FIXME on linux causes an error "Segmentation fault"`
- Linha 1487: `//          ->addFlag("snon") // @FIXME on linux causes a error "Segmentation fault"`
- Linha 1506: `// @TODO use native tar?`

### 2.2 Propriedade `idleTimeout` Nao Utilizada
**Arquivo:** `src/SevenZip.php:128`

A propriedade `$idleTimeout` e definida mas nunca utilizada no codigo. Deve ser implementada ou removida:

```php
protected int $idleTimeout = 120; // Definido mas nao usado
```

### 2.3 Inconsistencia nos Tipos de Retorno
Alguns metodos retornam `self`, outros `static`, e outros `SevenZip`:

```php
public function format(string $format): self          // usa self
public function setFormat(?string $format): self      // usa self
public function setCustomFlags(array $customFlags): SevenZip  // usa nome da classe
public function faster(): self                        // usa self
```

**Recomendacao:** Padronizar usando `static` para melhor suporte a heranca.

### 2.4 Validacao de Parametros Ausente
Metodos como `mx()`, `mpass()`, `mfb()` nao validam os valores de entrada:

```php
public function mx(int $level): self
{
    // Falta: validar se $level esta entre 0 e 9 (ou 22 para zstd)
    return $this->addFlag("mx", $level);
}
```

### 2.5 Magic Strings
Muitas strings sao usadas diretamente no codigo. Constantes melhorariam a manutencao:

```php
// Atual
$this->format("7z");
if ($this->getFormat() === "zip") { ... }

// Sugerido
class Format {
    public const SEVEN_ZIP = '7z';
    public const ZIP = 'zip';
    public const TAR = 'tar';
    // ...
}
```

---

## 3. Tratamento de Erros

### 3.1 Uso de `@` para Supressao de Erros
**Arquivo:** `src/SevenZip.php`

- Linha 941: `@unlink($sourcePath);`
- Linha 1504: `@unlink($tarPath);`

Usar tratamento de excecoes adequado em vez de suprimir erros.

### 3.2 Excecoes Genericas
O codigo lanca `\RuntimeException` generica:

```php
// Linha 563-567
throw new \RuntimeException(
    "Command: " . implode(" ", $command) . "\n" ...
);
```

Criar excecoes especificas para diferentes tipos de erro.

### 3.3 Falta de Validacao no Construtor
Quando `$sevenZipPath === true` e o executavel nao e encontrado, o codigo silenciosamente tenta usar o binario do pacote:

```php
if ($sevenZipPath === TRUE) {
    $finder = new ExecutableFinder();
    $sevenZipPath = $finder->find("7z");
    if ($sevenZipPath !== NULL) {
        $this->setSevenZipPath($sevenZipPath);
    }
    // Se nao encontrar, nao lanca excecao aqui
}
```

---

## 4. Testes

### 4.1 Cobertura de Testes Incompleta
**Arquivo:** `tests/SevenZipTest.php`

Faltam testes para:
- Cenarios de erro (arquivo inexistente, permissao negada, disco cheio)
- Timeout de operacoes
- Arquivos corrompidos
- Senhas incorretas
- Formatos nao suportados
- Metodos: `getInfo()`, `parseInfoOutput()`, `parseFileInfoOutput()`
- Valores limite (arquivos muito grandes, nomes com caracteres especiais)

### 4.2 Testes Dependentes de Ordem
Uso de `#[Depends]` cria dependencias entre testes que podem causar falhas em cascata:

```php
#[Depends('testTarBeforeExplicit')]
public function testAutoUntar($tarPath) { ... }

#[Depends('testTarBeforeExplicit')]
public function testNotAutoUntar($tarPath) { ... }

#[Depends('testTarBeforeExplicit')]
public function testDeleteSourceAfterExtract($tarPath) { ... }
```

### 4.3 Limpeza de Arquivos de Teste
A limpeza ocorre apenas em `tearDownAfterClass()`, podendo deixar arquivos orfaos em caso de falha.

### 4.4 Falta de Testes de Integracao para Windows
O workflow do GitHub Actions executa apenas em `ubuntu-latest`:

```yaml
runs-on: ubuntu-latest
```

Considerar adicionar matriz de testes:
```yaml
runs-on: ${{ matrix.os }}
strategy:
  matrix:
    os: [ubuntu-latest, macos-latest]
    php: ['8.2', '8.3', '8.4']
```

---

## 5. Seguranca

### 5.1 Exposicao de Senha em Logs
A senha e incluida diretamente no comando que pode aparecer em logs de erro:

```php
// Linha 1348
$this->addFlag("p", $this->getPassword(), glued: TRUE);
```

A mensagem de erro exibe o comando completo:
```php
throw new \RuntimeException(
    "Command: " . implode(" ", $command) . "\n" ...  // Senha visivel!
);
```

**Recomendacao:** Mascarar a senha em mensagens de erro.

### 5.2 Validacao de Path Injection
Nao ha validacao adequada dos caminhos de origem/destino que podem conter caracteres maliciosos ou path traversal:

```php
public function setSourcePath(string $path): self
{
    $this->sourcePath = $path;  // Sem validacao
    return $this;
}
```

### 5.3 Arquivos Temporarios
**Arquivo:** `src/SevenZip.php:1462`

Arquivos temporarios sao criados em diretorio previsivel:
```php
$tarPath = sys_get_temp_dir() . '/' . uniqid('sevenzip_') . '/' ...
```

Considerar usar `tempnam()` com permissoes restritas.

---

## 6. Performance

### 6.1 Multiplas Chamadas a `getInfo()`
O metodo `getParsedInfo()` chama `getInfo()` que executa um processo externo. Resultado deveria ser cacheado:

```php
protected function getParsedInfo(?string $output = NULL): array
{
    return $this->parseInfoOutput($output ?? $this->getInfo()); // Executa 7z toda vez
}
```

### 6.2 Iteracao Multipla sobre Linhas
Em `parseInfoOutput()`, o array de linhas e iterado 3 vezes separadamente:

```php
foreach ($lines as $line) { ... }  // Para formats
// ...
foreach ($lines as $line) { ... }  // Para codecs
// ...
foreach ($lines as $line) { ... }  // Para hashers
```

Poderia ser otimizado para uma unica iteracao.

### 6.3 Regex Compilado
Regex patterns sao compilados em cada chamada. Considerar constantes estaticas.

---

## 7. Compatibilidade e Portabilidade

### 7.1 Suporte a Windows Incompleto
**Arquivo:** `src/SevenZip.php:236-240`

Windows nao e suportado:
```php
$os = match (PHP_OS_FAMILY) {
    "Darwin" => "mac",
    "Linux" => "linux",
    default => NULL,  // Windows retorna NULL
};
```

### 7.2 Binarios Desatualizados
Os binarios incluidos sao da versao 2403. Considerar:
- Processo de atualizacao automatizado
- Download sob demanda dos binarios

### 7.3 Arquiteturas Nao Suportadas
**Arquivo:** `src/SevenZip.php:242-248`

Arquiteturas como RISC-V nao sao suportadas:
```php
$arch = match (php_uname("m")) {
    "x86_64" => "x64",
    "x86" => "x86",
    "arm64", "aarch64" => "arm64",
    "arm" => "arm",
    default => NULL,  // RISC-V, etc. retornam NULL
};
```

---

## 8. Documentacao

### 8.1 PHPDoc Incompletos
Alguns metodos tem documentacao incompleta ou ausente:

```php
public function mmem(int|string $size = 24)  // Falta @return
{
    return $this->addFlag("mmem", $size);
}
```

### 8.2 README Desatualizado
- Secao "Documentation / API" aparece duplicada (linhas 179-181)
- Alguns tipos de retorno na documentacao nao correspondem ao codigo
- `fileInfo()` e `fileList()` documentados como retornando `string`, mas retornam `array`

### 8.3 Changelog Ausente
Nao ha arquivo CHANGELOG.md para rastrear mudancas entre versoes.

---

## 9. Configuracao e CI/CD

### 9.1 Ferramentas de Qualidade Ausentes
Nao ha configuracao para:
- PHP CS Fixer ou PHP_CodeSniffer (style guide)
- PHPStan ou Psalm (analise estatica)
- PHP Mess Detector

### 9.2 GitHub Actions Limitado
**Arquivo:** `.github/workflows/phpunit.yml`

Apenas uma versao do PHP e testada:
```yaml
php-version: '8.3'
```

Recomendacao: testar com 8.2, 8.3 e 8.4.

### 9.3 Sem Code Coverage
Nao ha geracao de relatorios de cobertura de codigo nos testes.

---

## 10. API e Usabilidade

### 10.1 Metodos Fluentes Inconsistentes
Alguns metodos `set*` retornam `SevenZip` (nome da classe) enquanto outros retornam `self`:

```php
public function setTimeout(int $timeout): SevenZip    // Nome da classe
public function setSourcePath(string $path): self     // self
```

### 10.2 Metodo `target()` Aceita `null`
**Arquivo:** `src/SevenZip.php:1257`

```php
public function target(?string $path): self
```

Permitir `null` pode causar erros silenciosos. Considerar remover.

### 10.3 Duplicacao de Metodos
Existem metodos duplicados que fazem a mesma coisa:
- `source()` e `setSourcePath()`
- `target()` e `setTargetPath()`
- `encrypt()` e `setPassword()`
- `decrypt()` e `setPassword()`
- `progress()` e `setProgressCallback()`
- `deleteSourceAfterCompress()` e `sdel()`
- `solid()` e `ms()`

---

## 11. Melhorias de Recursos

### 11.1 Suporte a Streams
Nao ha suporte para compressao/extracao de streams, apenas arquivos em disco.

### 11.2 Operacoes Assincronas
Nao ha suporte para compressao/extracao assincrona.

### 11.3 Verificacao de Integridade
O comando `7z t` (test) nao esta implementado, apesar de listado no TODO.

### 11.4 Atualizacao de Arquivos
Nao ha suporte para adicionar/remover arquivos de um arquivo existente.

### 11.5 Listagem de Volumes
Nao ha suporte para arquivos multi-volume (split archives).

---

## Resumo de Prioridades

| Prioridade | Area | Descricao |
|------------|------|-----------|
| Alta | Seguranca | Mascarar senha em logs de erro |
| Alta | Arquitetura | Refatorar classe monolitica |
| Alta | Testes | Aumentar cobertura de testes |
| Media | Codigo | Remover codigo comentado |
| Media | Compatibilidade | Adicionar suporte a Windows |
| Media | CI/CD | Matriz de testes multi-versao |
| Media | Erros | Hierarquia de excecoes |
| Baixa | Performance | Cache de informacoes do 7z |
| Baixa | Documentacao | Corrigir documentacao desatualizada |
| Baixa | Recursos | Implementar comando test |

---

## Proximos Passos Sugeridos

1. **Fase 1 - Correcoes Criticas**
   - Mascarar senhas em mensagens de erro
   - Remover codigo comentado
   - Implementar `idleTimeout` ou remover propriedade

2. **Fase 2 - Qualidade**
   - Adicionar PHPStan/Psalm
   - Configurar PHP CS Fixer
   - Aumentar cobertura de testes para 80%+

3. **Fase 3 - Refatoracao**
   - Extrair classes especializadas
   - Criar hierarquia de excecoes
   - Definir interfaces

4. **Fase 4 - Recursos**
   - Implementar comando test
   - Adicionar suporte a Windows
   - Implementar cache de informacoes

---

*Documento gerado em: 2025-11-23*
*Versao analisada: commit f40e182*
