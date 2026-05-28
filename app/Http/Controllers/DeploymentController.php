<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Deployment;
use App\Models\Item;
use App\Models\User;
use App\Events\ItemUpdated;
use App\Notifications\DeploymentApproved;
use App\Notifications\DeploymentRejected;
use App\Notifications\DeploymentRequested;
use App\Notifications\ProductionDeployCompleted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class DeploymentController extends Controller
{
    /**
     * Página /deploys — board com 4 colunas (pendentes, aprovados,
     * em produção, rejeitados).
     */
    public function index(): Response
    {
        $deployments = Deployment::with([
                'item:id,title,project_id,column_id',
                'item.project:id,name',
                'deployer:id,name,email',
                'approver:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (Deployment $d) => $this->serializeDeployment($d));

        return Inertia::render('Deploys/Index', [
            'deployments' => $deployments,
        ]);
    }

    /**
     * Cria um novo deploy. Pode ser:
     *   - staging (vai pra aprovação)
     *   - production normal (linkado a um staging approved)
     *   - production urgente (pula homologação)
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'environment' => ['required', 'in:staging,production'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'linked_deployment_id' => ['nullable', 'exists:deployments,id'],
            'is_urgent' => ['boolean'],
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Só cards top-level em "Feito" podem ter deploy.
        if ($item->parent_id) {
            return back()->withErrors(['item_id' => 'Subtarefas não podem ter deploys diretos. Use o card pai.']);
        }
        $doneColumnId = Column::where('name', 'Feito')->value('id');
        if (! $doneColumnId || $item->column_id != $doneColumnId) {
            return back()->withErrors(['item_id' => 'Apenas cards em "Feito" podem ter deploys.']);
        }

        // Trava: não permitir staging se o card já tem deploy de produção ativo
        //        (a regra que o user definiu — evitar deploys duplicados).
        $hasActiveProductionDeploy = Deployment::where('item_id', $item->id)
            ->where('environment', Deployment::ENV_PRODUCTION)
            ->where('status', Deployment::STATUS_COMPLETED)
            ->exists();
        if ($hasActiveProductionDeploy) {
            return back()->withErrors(['item_id' => 'Este card já tem deploy de produção registrado.']);
        }

        $isUrgent = (bool) ($validated['is_urgent'] ?? false);
        $env = $validated['environment'];

        return DB::transaction(function () use ($validated, $item, $env, $isUrgent) {
            if ($env === Deployment::ENV_STAGING) {
                $deployment = Deployment::create([
                    'item_id' => $item->id,
                    'deployer_id' => Auth::id(),
                    'environment' => Deployment::ENV_STAGING,
                    'status' => Deployment::STATUS_PENDING,
                    'notes' => $validated['notes'] ?? null,
                    'is_urgent' => false,
                ]);
                $this->notifyAdmins($deployment, new DeploymentRequested($deployment));
                // Real-time: outros boards abertos refletem o estado novo
                // (modal do card ganha label "deploy em andamento", esconde
                // os botões de solicitar de novo).
                broadcast(new ItemUpdated($item->fresh('deployments')))->toOthers();

                return back()->with('success', 'Deploy em homologação solicitado. Os admins foram notificados.');
            }

            // Production
            $linkedId = $validated['linked_deployment_id'] ?? null;
            if (! $isUrgent && ! $linkedId) {
                return back()->withErrors([
                    'linked_deployment_id' => 'Production sem urgência exige um deploy de staging aprovado pra linkar.',
                ]);
            }
            if ($linkedId) {
                $linked = Deployment::find($linkedId);
                if (! $linked || ! $linked->isApproved() || $linked->item_id !== $item->id) {
                    return back()->withErrors([
                        'linked_deployment_id' => 'Deploy de staging linkado não está aprovado ou não pertence a este card.',
                    ]);
                }
            }

            $deployment = Deployment::create([
                'item_id' => $item->id,
                'deployer_id' => Auth::id(),
                'environment' => Deployment::ENV_PRODUCTION,
                'status' => Deployment::STATUS_COMPLETED,
                'notes' => $validated['notes'] ?? null,
                'linked_deployment_id' => $linkedId,
                'is_urgent' => $isUrgent,
            ]);

            $this->notifyAssignees($deployment, new ProductionDeployCompleted($deployment));
            if ($isUrgent) {
                $this->notifyAdmins($deployment, new ProductionDeployCompleted($deployment));
            }
            broadcast(new ItemUpdated($item->fresh('deployments')))->toOthers();

            $msg = $isUrgent
                ? 'Deploy URGENTE em produção registrado.'
                : 'Deploy em produção registrado.';
            return back()->with('success', $msg);
        });
    }

    /**
     * Admin aprova um deploy de staging. Notifica deployer + assignees.
     */
    public function approve(Request $request, Deployment $deployment): RedirectResponse
    {
        $this->authorizeAdmin();

        if (! $deployment->isStaging() || ! $deployment->isPending()) {
            return back()->withErrors(['general' => 'Só deploys de homologação pendentes podem ser aprovados.']);
        }

        $deployment->update([
            'status' => Deployment::STATUS_APPROVED,
            'approver_id' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->notifyAssignees($deployment->fresh(['item.assignees', 'deployer', 'approver']),
            new DeploymentApproved($deployment->fresh()));
        // Também notifica o deployer pra ele saber que pode promover.
        $deployment->deployer?->notify(new DeploymentApproved($deployment->fresh()));
        broadcast(new ItemUpdated($deployment->item->fresh('deployments')))->toOthers();

        return back()->with('success', 'Deploy aprovado. Deployer e responsáveis foram notificados.');
    }

    /**
     * Admin rejeita um deploy de staging. Motivo obrigatório.
     */
    public function reject(Request $request, Deployment $deployment): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if (! $deployment->isStaging() || ! $deployment->isPending()) {
            return back()->withErrors(['general' => 'Só deploys de homologação pendentes podem ser rejeitados.']);
        }

        $deployment->update([
            'status' => Deployment::STATUS_REJECTED,
            'approver_id' => Auth::id(),
            'rejected_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        $deployment->deployer?->notify(new DeploymentRejected($deployment->fresh()));
        broadcast(new ItemUpdated($deployment->item->fresh('deployments')))->toOthers();

        return back()->with('success', 'Deploy rejeitado. O deployer foi notificado.');
    }

    private function notifyAdmins(Deployment $deployment, $notification): void
    {
        // Filtro chave: NÃO notifica o próprio deployer mesmo que ele seja admin.
        $admins = User::where('is_admin', true)
            ->whereNull('deleted_at')
            ->where('id', '!=', $deployment->deployer_id)
            ->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        }
    }

    private function notifyAssignees(Deployment $deployment, $notification): void
    {
        $item = $deployment->item;
        if (! $item) return;

        // Carrega assignees se ainda não veio.
        if (! $item->relationLoaded('assignees')) {
            $item->load('assignees');
        }
        // Não notifica o deployer (mesmo se for assignee).
        $recipients = $item->assignees->where('id', '!=', $deployment->deployer_id);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    private function authorizeAdmin(): void
    {
        if (! Auth::user()?->is_admin) {
            abort(403, 'Apenas admins podem aprovar ou rejeitar deploys.');
        }
    }

    private function serializeDeployment(Deployment $d): array
    {
        return [
            'id' => $d->id,
            'environment' => $d->environment,
            'status' => $d->status,
            'notes' => $d->notes,
            'is_urgent' => $d->is_urgent,
            'created_at' => $d->created_at?->toIso8601String(),
            'approved_at' => $d->approved_at?->toIso8601String(),
            'rejected_at' => $d->rejected_at?->toIso8601String(),
            'rejection_reason' => $d->rejection_reason,
            'linked_deployment_id' => $d->linked_deployment_id,
            'item' => $d->item ? [
                'id' => $d->item->id,
                'title' => $d->item->title,
                'project_name' => $d->item->project?->name,
            ] : null,
            'deployer' => $d->deployer ? [
                'id' => $d->deployer->id,
                'name' => $d->deployer->name,
            ] : null,
            'approver' => $d->approver ? [
                'id' => $d->approver->id,
                'name' => $d->approver->name,
            ] : null,
        ];
    }
}
