<?php
declare(strict_types=1);

namespace KronoConnect\Models;

class AdminModel extends BaseModel
{
    protected string $table = 'settings';

    /**
     * Récupère tous les paramètres système.
     */
    public function getSettings(): array
    {
        try {
            $t = $this->db->t($this->table);
            $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM `{$t}`");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Met à jour ou crée un paramètre.
     */
    public function setSetting(string $key, string $value): void
    {
        $t = $this->db->t($this->table);
        $existing = $this->db->fetchOne("SELECT id FROM `{$t}` WHERE setting_key = ?", [$key]);
        if ($existing) {
            $this->db->update($this->table, ['setting_value' => $value], ['setting_key' => $key]);
        } else {
            $this->db->insert($this->table, [
                'setting_key'   => $key,
                'setting_value' => $value
            ]);
        }
    }
}
