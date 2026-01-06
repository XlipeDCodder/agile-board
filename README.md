# Documentação Técnica - Agile Board

## Visão Geral
O **Agile Board** é uma aplicação de gerenciamento de projetos baseada em quadros Kanban, desenvolvida para facilitar o acompanhamento de tarefas, bugs e projetos em tempo real. A aplicação permite a criação de colunas personalizáveis, atribuição de múltiplos responsáveis, rastreamento de tempo, comentários com anexos e visualização de métricas detalhadas.

## Stack Tecnológico

### Backend
- **Framework**: Laravel 11.x
- **Linguagem**: PHP 8.2+
- **Banco de Dados**: Qualquer um, recomendo Postgres
- **Real-time**: Laravel Reverb (WebSocket)
- **Autenticação**: Laravel Breeze

### Frontend
- **Framework**: Vue.js 3 (Composition API)
- **Roteamento/Hydration**: Inertia.js
- **Estilização**: Tailwind CSS
- **Componentes**: Headless UI, Vue Multiselect, Vuedraggable
- **Gráficos**: Chart.js / Vue-Chartjs

## Funcionalidades Principais

### 1. Quadro Kanban
- **Drag & Drop**: Movimentação de cards entre colunas com atualização em tempo real.
- **Colunas Dinâmicas**: Gerenciamento de colunas (To Do, In Progress, Done, etc.).
- **Cards Detalhados**: Suporte a prioridade, estimativa (pontos), data de vencimento, e tipo (Tarefa/Bug).
- **Subtarefas**: Checklists dentro de cada card.

### 2. Gestão de Projetos
- **CRUD de Projetos**: Criação e edição de projetos com prazos definidos.
- **Status de Projeto**: Marcação de projetos como Concluídos ou Em Andamento.
- **Associação**: Itens do quadro podem ser vinculados a projetos específicos.

### 3. Rastreamento de Tempo (Time Tracking)
- **Apontamento de Horas**: Registro de tempo gasto em cada item.
- **Calendário**: Visualização mensal das horas trabalhadas.
- **Validação**: Limite diário de 10 horas por usuário.

### 4. Dashboard e Métricas
- **Visão Geral**: Gráficos unificados de projetos (Total, Concluídos, Atrasados).
- **Produtividade**: Ranking de entregas (Gamification).
- **Tempo**: Gráficos de horas trabalhadas por projeto (Global e Usuário).
- **Alertas**: Identificação de usuários ociosos e itens sem responsável.

### 5. Colaboração em Tempo Real
- **WebSockets**: Utiliza Laravel Reverb para sincronizar o quadro instantaneamente entre todos os usuários conectados.
- **Eventos**: `ItemMoved` dispara atualizações automáticas via canal privado `board`.

## Estrutura do Banco de Dados (Principais Tabelas)

- `users`: Usuários do sistema.
- `projects`: Projetos com `name`, `description`, `due_date`, `status`.
- `columns`: Colunas do quadro com `order` para organização.
- `items`: Tarefas/Cards principais.
    - FKs: `column_id`, `project_id`, `creator_id`.
    - Campos: `priority`, `type`, `estimation`.
- `item_user`: Tabela pivot para atribuição múltipla de responsáveis.
- `subtasks`: Sub-itens vinculados a um `item_id`.
- `comments`: Comentários em itens (suporta anexos via `comment_attachments`).
- `time_entries`: Registros de horas trabalhadas (`minutes`, `date`).

## Instalação e Configuração

### Requisitos
- PHP 8.2+
- Node.js & NPM
- Composer

### Passos
1.  **Clone o repositório**:
    ```bash
    git clone <url-do-repo>
    cd agile-board
    ```

2.  **Instale dependências de Backend**:
    ```bash
    composer install
    ```

3.  **Instale dependências de Frontend**:
    ```bash
    npm install
    ```

4.  **Configure o ambiente**:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *Certifique-se de configurar `BROADCAST_CONNECTION=reverb` no `.env`.*

5.  **Execute as migrações**:
    ```bash
    php artisan migrate
    ```

6.  **Inicie os servidores(Isso é desenvolvimento, para prod use nginx e php-fpm e um serviço em background para o reverb)**:
    *Terminal 1 (Laravel)*: `php artisan serve`
    *Terminal 2 (Vite)*: `npm run dev`
    *Terminal 3 (Reverb)*: `php artisan reverb:start`

7.  **Dicas extras**:
    *Ao rodar as migrations, não será criado um user admin, para isso ao registrar um user, entre no banco de dados e mude o campo `is_admin` para `1` ou crie um seeder(acredite no seu potencial).
    *O sistema é baseado em projetos, antes de criar um item, crie um projeto para garantir as melhores metricas no dashboard.
    *A coluna "Feito" é usada para marcar itens como concluídos e disparar o auto arquivamento do item, então garanta a existência de uma coluna "Feito".
    *O sistema possui um limite de 10 horas por usuário por dia, para alterar isso da uma olhada no código pois nessa altura do campeonato só Deus sabe como eu fiz.

## Fluxo de Real-time (Detalhe Técnico)
1.  **Ação**: Usuário move um card no Frontend.
2.  **Request**: `BoardController::reorder` é chamado.
3.  **Processamento**: Banco de dados é atualizado.
4.  **Evento**: `ItemMoved` é disparado (implementa `ShouldBroadcastNow`).
5.  **Broadcasting**: Laravel envia payload para o servidor Reverb.
6.  **Listening**: Clientes conectados via `Laravel Echo` no canal `board` recebem o evento `.item.moved`.
7.  **Reação**: Frontend recarrega as colunas (`router.reload({ only: ['columns'] })`).
