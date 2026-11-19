<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validation serveur simple : regles separees par "|".
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public static function validate(array $data, array $rules): array
    {
        $validator = new self();

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            foreach (explode('|', $ruleString) as $rule) {
                $validator->apply($field, $value, $rule, $data);
            }
        }

        return $validator->errors;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $data
     */
    private function apply(string $field, mixed $value, string $rule, array $data): void
    {
        if (isset($this->errors[$field])) {
            return;
        }

        $label = self::label($field);

        if ($rule === 'required') {
            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                $this->errors[$field] = $label . ' est obligatoire.';
            }
            return;
        }

        if ($rule === 'nullable') {
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if (is_numeric($value) && (float) $value < $min) {
                $this->errors[$field] = sprintf('%s doit etre superieur ou egal a %d.', $label, $min);
            } elseif (!is_numeric($value) && mb_strlen((string) $value) < $min) {
                $this->errors[$field] = sprintf('%s doit contenir au moins %d caracteres.', $label, $min);
            }
            return;
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (mb_strlen((string) $value) > $max) {
                $this->errors[$field] = sprintf('%s ne doit pas depasser %d caracteres.', $label, $max);
            }
            return;
        }

        if (str_starts_with($rule, 'in:')) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array((string) $value, $allowed, true)) {
                $this->errors[$field] = $label . ' contient une valeur invalide.';
            }
            return;
        }

        if ($rule === 'email' && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = $label . ' doit etre une adresse email valide.';
            return;
        }

        if ($rule === 'date') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
            if ($date === false || $date->format('Y-m-d') !== (string) $value) {
                $this->errors[$field] = $label . ' doit etre une date valide (AAAA-MM-JJ).';
            }
            return;
        }

        if ($rule === 'datetime') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $value);
            if ($date === false) {
                $this->errors[$field] = $label . ' doit etre une date et heure valides.';
            }
            return;
        }

        if ($rule === 'decimal' && !is_numeric($value)) {
            $this->errors[$field] = $label . ' doit etre un nombre.';
            return;
        }

        if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = $label . ' doit etre un entier.';
            return;
        }

        if ($rule === 'telephone') {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
            if (strlen($digits) < 6 || strlen($digits) > 15) {
                $this->errors[$field] = $label . ' doit contenir entre 6 et 15 chiffres.';
            }
            return;
        }

        if ($rule === 'confirme' && ($data[$field . '_confirmation'] ?? null) !== $value) {
            $this->errors[$field] = 'La confirmation ne correspond pas.';
            return;
        }

        if (str_starts_with($rule, 'apres:')) {
            $other = substr($rule, 6);
            $otherValue = $data[$other] ?? null;
            if (is_string($otherValue) && $otherValue !== '' && (string) $value <= $otherValue) {
                $this->errors[$field] = $label . ' doit etre posterieur a ' . self::label($other) . '.';
            }
            return;
        }

        if (str_starts_with($rule, 'motdepasse:')) {
            if (mb_strlen((string) $value) < 8 || !preg_match('/[A-Za-z]/', (string) $value) || !preg_match('/\d/', (string) $value)) {
                $this->errors[$field] = 'Le mot de passe doit contenir au moins 8 caracteres, une lettre et un chiffre.';
            }
            return;
        }
    }

    private static function label(string $field): string
    {
        $labels = [
            'nom' => 'Le nom',
            'prenom' => 'Le prénom',
            'matricule' => 'Le matricule',
            'telephone' => 'Le téléphone',
            'sexe' => 'Le sexe',
            'date_naissance' => 'La date de naissance',
            'date_enregistrement' => "La date d'enregistrement",
            'frais_formation' => 'Les frais de formation',
            'description' => 'La description',
            'libelle' => 'Le libellé',
            'date_debut' => 'La date de début',
            'date_fin' => 'La date de fin',
            'etudiant_id' => "L'étudiant",
            'formation_id' => 'La formation',
            'annee_academique_id' => "L'année académique",
            'montant_total' => 'Le montant total',
            'mode_paiement' => 'Le mode de paiement',
            'montant' => 'Le montant',
            'date_paiement' => 'La date de paiement',
            'motif' => 'Le motif',
            'nom_utilisateur' => "Le nom d'utilisateur",
            'mot_de_passe' => 'Le mot de passe',
            'email' => "L'email",
            'role' => 'Le rôle',
            'observation' => "L'observation",
            'reference' => 'La référence',
            'montant_attendu' => 'Le montant attendu',
            'date_echeance' => "La date d'échéance",
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
