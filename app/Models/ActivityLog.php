<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Historique des actions sensibles (section 13) */
class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'store_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public const ACTIONS = [
        'stock.correction' => 'Correction de stock',
        'stock.transfert' => 'Transfert de stock',
        'stock.mouvement' => 'Mouvement de stock',
        'produit.prix' => 'Modification de prix',
        'produit.suppression' => 'Suppression de produit',
        'commande.annulation' => 'Annulation de commande',
        'commande.retour' => 'Retour de commande',
        'depense.modification' => 'Modification de dépense',
        'depense.suppression' => 'Suppression de dépense',
        'document.suppression' => 'Suppression de document',
        'client.suppression' => 'Suppression de client',
        'utilisateur.creation' => 'Création d’utilisateur',
        'utilisateur.modification' => 'Modification d’utilisateur',
        'magasin.creation' => 'Création de magasin',
        'magasin.modification' => 'Modification de magasin',
        'conflit.resolution' => 'Résolution de conflit',
        'connexion' => 'Connexion',
    ];

    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $storeId = null): self
    {
        $user = auth()->user();

        return self::create([
            'user_id' => $user?->id,
            'store_id' => $storeId ?? ($subject && isset($subject->store_id) ? $subject->store_id : $user?->store_id),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
