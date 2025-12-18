<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Column;
use App\Models\Item;

class BoardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpa as tabelas para evitar dados duplicados ao rodar o seeder várias vezes
        Item::query()->delete();
        Column::query()->delete();

        // Cria as colunas do quadro
        $backlog = Column::create(['name' => 'Backlog', 'order' => 1]);
        $todo = Column::create(['name' => 'A Fazer', 'order' => 2]);
        $doing = Column::create(['name' => 'Em Desenvolvimento', 'order' => 3]);
        $done = Column::create(['name' => 'Feito', 'order' => 4]);

        // Pega o primeiro usuário do banco. Se não houver nenhum, cria um usuário de teste.
        // Isso previne erros caso a tabela de usuários esteja vazia.
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Usuário Teste',
                'email' => 'teste@exemplo.com',
            ]);
        }

        // Cria alguns itens de exemplo para popular o quadro
        Item::create([
            'title' => 'Configurar ambiente de desenvolvimento',
            'description' => 'Instalar Laravel, Inertia, Vue e configurar o banco de dados.',
            'column_id' => $done->id,
            'creator_id' => $user->id,
            'status' => 'Feito',
            'priority' => 'Alta',
            'order_in_column' => 1,
        ]);

        Item::create([
            'title' => 'Criar componente do quadro Kanban',
            'description' => 'Desenvolver a estrutura principal do quadro com Vue.js.',
            'column_id' => $doing->id,
            'creator_id' => $user->id,
            'status' => 'Em Desenvolvimento',
            'priority' => 'Alta',
            'order_in_column' => 1,
        ]);

        Item::create([
            'title' => 'Implementar funcionalidade de Drag and Drop',
            'description' => 'Permitir que os usuários movam os cards entre as colunas.',
            'column_id' => $todo->id,
            'creator_id' => $user->id,
            'status' => 'A Fazer',
            'priority' => 'Média',
            'order_in_column' => 1,
        ]);
    }
}
