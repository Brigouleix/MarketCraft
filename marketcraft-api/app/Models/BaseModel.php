<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Socle commun aux modeles MarketCraft.
 *
 * La base est heritee de l'ancien back-end : tables et colonnes en francais,
 * pluriels irreguliers (« utilisateurs », « avis », « lignes_commande »).
 * Les conventions d'Eloquent — qui deduirait « utilisateur » de « User »
 * ou « avi » de « Avis » — ne s'appliquent pas. Chaque modele declare donc
 * son `$table` explicitement.
 */
abstract class BaseModel extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Le contrat impose « Y-m-d H:i:s ». Par defaut Eloquent serialise les
     * dates en ISO 8601 (« 2026-07-25T14:30:00.000000Z ») : le front les
     * afficherait telles quelles, avec le T et les microsecondes.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
