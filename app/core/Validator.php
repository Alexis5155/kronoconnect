<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Validateur de formulaires avec une API fluide.
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retourne vrai si la validation a réussi.
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Retourne le tableau des erreurs.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Ajoute une erreur pour un champ spécifique.
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    /**
     * Récupère la valeur d'un champ ou null si inexistant.
     */
    private function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    // ══ RÈGLES DE VALIDATION ═════════════════════════════════

    public function required(string $field, string $message = 'Ce champ est requis.'): self
    {
        $value = $this->getValue($field);
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, $message ?? "Ce champ doit contenir au moins {$min} caractères.");
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, $message ?? "Ce champ ne peut dépasser {$max} caractères.");
        }
        return $this;
    }

    public function email(string $field, string $message = 'L\'adresse e-mail est invalide.'): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function integer(string $field, string $message = 'Ce champ doit être un nombre entier.'): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function numeric(string $field, string $message = 'Ce champ doit être une valeur numérique.'): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function min(string $field, float|int $min, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && is_numeric($value) && (float)$value < $min) {
            $this->addError($field, $message ?? "La valeur doit être supérieure ou égale à {$min}.");
        }
        return $this;
    }

    public function max(string $field, float|int $max, string $message = null): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && is_numeric($value) && (float)$value > $max) {
            $this->addError($field, $message ?? "La valeur doit être inférieure ou égale à {$max}.");
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $message = 'Le format est invalide.'): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !preg_match($pattern, (string)$value)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    public function in(string $field, array $values, string $message = 'La valeur sélectionnée est invalide.'): self
    {
        $value = $this->getValue($field);
        if ($value !== null && $value !== '' && !in_array($value, $values, true)) {
            $this->addError($field, $message);
        }
        return $this;
    }

    /**
     * Vérifie l'unicité d'une valeur dans la base de données.
     * 
     * @param string $field Le champ du formulaire à valider
     * @param string $table La table en base de données
     * @param string $column La colonne dans la table (utilise le nom du champ si null)
     * @param mixed $excludeId Un ID à exclure lors de l'édition (optionnel)
     * @param string $idColumn Le nom de la colonne ID à exclure (par défaut 'id')
     */
    public function unique(string $field, string $table, string $column = null, mixed $excludeId = null, string $idColumn = 'id', string $message = 'Cette valeur est déjà utilisée.'): self
    {
        $value = $this->getValue($field);
        $col = $column ?? $field;
        
        if ($value === null || $value === '') {
            return $this;
        }

        $db = Database::getInstance()->getRawPdo();
        
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = ?";
        $params = [$value];

        if ($excludeId !== null) {
            $sql .= " AND `{$idColumn}` != ?";
            $params[] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            $this->addError($field, $message);
        }

        return $this;
    }
}
