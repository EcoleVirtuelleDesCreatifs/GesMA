<?php

namespace App\Exceptions;

use RuntimeException;

/** Règle de gestion non respectée (transition de statut interdite, etc.). */
class BusinessRuleException extends RuntimeException {}
