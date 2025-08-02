# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [0.0.1] - 2025-08-02

### Adicionado
- **Funcionalidade Principal**: Plugin WordPress para listar serviços P2P de Bitcoin que aceitam DePix
- **Shortcode [depix_p2ps]**: Exibe lista de serviços P2P com opções de personalização
  - Parâmetros suportados: `limit`, `search`, `orderby`, `order`, `style`
  - Filtros de busca por nome, descrição e contato
  - Ordenação por nome, valor mínimo e taxa
- **Sistema de Serviços Modulares**:
  - `DepixP2PService`: Gerenciamento e filtragem de dados P2P
  - `DepixShortcodeService`: Renderização de shortcodes
  - `DepixAjaxService`: Funcionalidades AJAX
  - `DepixAssetService`: Gerenciamento de assets CSS/JS
- **Cache Inteligente**: Sistema de cache para dados P2P com melhor performance
- **Dados Mock**: Arquivo JSON com dados de exemplo para serviços P2P
- **Interface de Busca**: Formulário de pesquisa e filtros integrados
- **Estilos Personalizáveis**: CSS base com suporte a temas customizados
- **Segurança**: Validação de dados e prevenção de acesso direto aos arquivos
- **Configurações do Plugin**:
  - API habilitada por padrão
  - Cache habilitado por padrão
  - Modo debug desabilitado por padrão
- **Compatibilidade**:
  - WordPress 5.8+
  - PHP 7.4+
  - Licença GPLv2 ou posterior

### Recursos Técnicos
- Autoloader para carregamento eficiente de classes
- Hooks de ativação/desativação do plugin
- Limpeza automática de cache na desativação
- Suporte a transients para cache temporário
- Enfileiramento adequado de scripts e estilos
- Estrutura de arquivos organizada e modular

### Arquivos Principais
- `depixplugin.php`: Arquivo principal do plugin
- `class.depixplugin.php`: Classe principal de inicialização
- `src/services/`: Diretório com todos os serviços modulares
- `src/mock/p2p.json`: Dados de exemplo dos serviços P2P
- `assets/`: Recursos CSS e JavaScript
- `README.md`: Documentação do projeto
- `LICENSE`: Licença GPL v2