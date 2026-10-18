<?php

namespace App\Exceptions;

use DomainException;

/** Erreur métier affichable à l'utilisateur (stock insuffisant, statut invalide, ...) */
class BusinessException extends DomainException
{
}
