# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [0.0.1] - 2025-08-02

### Adicionado
- Estrutura inicial do plugin Depix
- Arquivo principal `depixplugin.php`
- Classe `DepixPlugin` para inicialização do plugin
- Diretório `src/services/` para serviços modulares
- Diretório `assets/` para recursos CSS e JavaScript
- Arquivo `.htaccess` para segurança e configuração do plugin
- Arquivos `index.php` para evitar acesso direto

### Recursos Técnicos
- Uso de `add_action` para inicializar o plugin
- Proteção de arquivos sensíveis com `.htaccess`

### Arquivos Principais
- `depixplugin.php`: Arquivo principal do plugin
- `class.depixplugin.php`: Classe principal de inicialização
- `src/services/`: Diretório com todos os serviços modulares
- `assets/`: Recursos CSS e JavaScript
- `README.md`: Documentação do projeto
- `LICENSE`: Licença GPL v2