# Adicionando novos RequestMatchers

## Onde colocar a classe
Os novos `RequestMatchers` devem ser colocados na pasta `source/source/lib/matchers`.

## Como o `RequestMatcher::build` mapeia o tipo
A função `RequestMatcher::build` utiliza `StringUtils::toStudlyCase` para converter o tipo em uma classe específica de `RequestMatcher`. Isso permite que o sistema reconheça o tipo baseado na string fornecida.

## Como registrá-lo
Para registrar um novo `RequestMatcher`, você precisará adicionar uma linha correspondente no arquivo `source/source/loader.php`, seguindo a estrutura dos outros `RequestMatchers` já existentes.

## Como escrever testes unitários
Os testes unitários para cada `RequestMatcher` devem ser escritos na pasta `source/tests/unit/lib/matchers`. É importante garantir que todos os aspectos do `RequestMatcher` sejam testados.

## Exemplo de configuração
Um exemplo de configuração pode ser encontrado em `docker_volumes/configuration/configure.php`, onde os `RequestMatchers` são utilizados para definir comportamentos específicos.

### Exemplo de código:
```php
// Exemplo de registro de RequestMatcher
$requestMatcher = new MyRequestMatcher();
$requestMatcher->register();
```
