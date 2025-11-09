# Painel Admin — Guia de UI

Este guia resume os padrões que sustentam o shell moderno do painel administrativo. O objetivo é manter consistência visual sem alterar regras de negócio, rotas ou chamadas existentes.

## Tokens & Temas

- Variáveis base (`:root`): `--bg`, `--bg-elevated`, `--fg`, `--muted`, `--border`, `--primary`, `--success`, `--warning`, `--danger`, `--card-radius`, `--sidebar-width`.
- Tema escuro é aplicado adicionando `data-theme="dark"` ao elemento `<html>`. Tokens alternativos já estão definidos em `assets/admin.css`.
- O seletor de tema (`[data-theme-toggle]`) persiste a escolha em `localStorage` (`admin.theme`). É seguro chamar `setAttribute('data-theme', 'light|dark')` manualmente se precisar.

## Grid & Shell

- `.app-container`: wrapper flex que abriga sidebar e conteúdo; usa `data-sidebar-state="expanded|collapsed"` tanto para desktop (recolher largura) quanto para mobile (off-canvas).
- `.app-sidebar`: navegação lateral com botões ícone+texto (`.app-nav a > i + span`). Use o mesmo markup para novos itens.
- `.app-topbar`: cabeçalho fixo com breadcrumbs, título (`.topbar-title`), busca (`.topbar-search`) e ações (`.topbar-actions`).
- `.app-main`: área de conteúdo com padding fluido (`2rem` desktop, `1.25rem` mobile). Use cards para seções destacadas.
- `.dashboard-footer`: rodapé mínimo que exibe versão do sistema e identificação da loja.

### Breakpoints

- ≤1024px: sidebar vira off-canvas; botão hambúrguer (`[data-sidebar-mobile-trigger]`) abre o menu; backdrop (`.sidebar-backdrop`) fecha com clique ou `Esc`.
- ≤640px: espaçamentos da topbar e da área principal diminuem; busca expande 100%.

## Componentes de UI

- **Cards**: `.card` ou `.settings-panel` em formulários. Cards métricos usam `.metric-card` + `metric-card__label/value/trend`.
- **Tabelas**: use `.table-responsive` + `.data-table` para ganhar cabeçalho sticky, zebra e paginação (`.table-pagination`).
- **Filtros & Chips**: `.filter-group`, `.filter-chip`, `.chips`, `.chip` para filtros selecionáveis e estados “ativo”.
- **Botões**: `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-alt`, `.btn-sm`. Os botões icônicos usam `.icon-btn`.

## Acessibilidade

- `skip link`: `<a class="sr-only" href="#main-content">` já incluído.
- Sidebar toggle (`data-sidebar-toggle`) e tema toggle (`data-theme-toggle`) possuem `aria-label`/`aria-pressed`. Preserve esses atributos em novos botões.
- Breadcrumbs e títulos seguem hierarquia `span > h1`. Em páginas com subtítulos use `<h2>` dentro de `.app-main`.

## Como usar o tema escuro

1. Clique no botão de lua/sol na topbar para alternar. O estado fica salvo e aplicado via `localStorage`.
2. Para forçar um tema em uma página específica, defina `document.documentElement.dataset.theme = 'dark';`.
3. Se precisar resetar via código, basta remover `admin.theme` do `localStorage`.

## Boas práticas

- Evite definir larguras fixas; prefira `max-width` e flex/grid.
- Use `word-wrap: break-word` ou classes existentes quando exibir textos longos (nomes de produtos, emails, IDs).
- Para novas tabelas complexas, mantenha o markup dentro de `.table-responsive` para evitar overflow horizontal.
- Sempre que criar novas ações no topo, use `.topbar-actions` para garantir alinhamento e responsividade.
